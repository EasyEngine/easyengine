<?php

namespace EE;

/**
 * Run a system process, and learn what happened.
 */
class Process {

	/**
	 * @param string $command Command to execute.
	 * @param string $cwd Directory to execute the command in.
	 * @param array $env Environment variables to set when running the command.
	 */
	public static function create( $command, $cwd = null, $env = array() ) {
		$proc = new self;

		$proc->command = $command;
		$proc->cwd = $cwd;
		$proc->env = $env;

		return $proc;
	}

	private $command, $cwd, $env;

	private function __construct() {}

	/**
	 * Run the command.
	 *
	 * @return ProcessRun
	 */
	public function run( $write_log = false ) {
		$cwd = $this->cwd;

		$descriptors = array(
			0 => STDIN,
			1 => array( 'pipe', 'w' ),
			2 => array( 'pipe', 'w' ),
		);

		if ( $write_log ) {
			$descriptors[1] = array( "file", EE_DEBUG_LOG_FILE, "a" );
			$descriptors[2] = array( "file", EE_DEBUG_LOG_FILE, "a" );
		}

		$proc = proc_open( $this->command, $descriptors, $pipes, $cwd, $this->env );

		$process_args = array(
			'command' => $this->command,
			'cwd' => $cwd,
			'env' => $this->env
		);

		if( false == $write_log ) {

			$stdout = stream_get_contents( $pipes[1] );
			self::write_log( $stdout );
			fclose( $pipes[1] );

			$process_args['stdout'] = $stdout;

			$stderr = stream_get_contents( $pipes[2] );
			self::write_log( $stderr );
			fclose( $pipes[2] );

			$process_args['stderr'] = $stderr;
		}

		$process_args['return_code'] = proc_close( $proc );
		return new ProcessRun( $process_args );
	}

	public static function write_log( $message ) {
		$log_file   = fopen( EE_DEBUG_LOG_FILE, "a" );
		fwrite( $log_file, "\n" . $message );
		fclose( $log_file );
	}

	/**
	 * Run the command, but throw an Exception on error.
	 *
	 * @return ProcessRun
	 */
	public function run_check() {
		$r = $this->run();

		if ( $r->return_code || !empty( $r->STDERR ) ) {
			throw new \RuntimeException( $r );
		}

		return $r;
	}
}

/**
 * Results of an executed command.
 */
class ProcessRun {

	/**
	 * @var array $props Properties of executed command.
	 */
	public function __construct( $props ) {
		foreach ( $props as $key => $value ) {
			$this->$key = $value;
		}
	}

	/**
	 * Return properties of executed command as a string.
	 *
	 * @return string
	 */
	public function __toString() {
		$out  = "$ $this->command\n";
		$out .= "$this->stdout\n$this->stderr";
		$out .= "cwd: $this->cwd\n";
		$out .= "exit status: $this->return_code";

		return $out;
	}

}
