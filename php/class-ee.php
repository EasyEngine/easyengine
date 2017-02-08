<?php

use \EE\Utils;
use \EE\Dispatcher;
use \EE\Process;

/**
 * Various utilities for ee-cli commands.
 */
class EE {

	private static $configurator;

	private static $logger;

	private static $hooks = array(), $hooks_passed = array();

	/**
	 * Set the logger instance.
	 *
	 * @param object $logger
	 */
	public static function set_logger( $logger ) {
		self::$logger = $logger;
	}

	/**
	 * Get the Configurator instance
	 *
	 * @return \EE\Configurator
	 */
	public static function get_configurator() {
		static $configurator;

		if ( ! $configurator ) {
			$configurator = new EE\Configurator( EE_ROOT . '/php/config-spec.php' );
		}

		return $configurator;
	}

	public static function get_root_command() {
		static $root;

		if ( ! $root ) {
			$root = new Dispatcher\RootCommand;
		}

		return $root;
	}

	public static function get_runner() {
		static $runner;

		if ( ! $runner ) {
			$runner = new EE\Runner;
		}

		return $runner;
	}

	/**
	 * Set the context in which ee-cli should be run
	 */
	public static function set_url( $url ) {
		EE::debug( 'Set URL: ' . $url );
		$url_parts = Utils\parse_url( $url );
		self::set_url_params( $url_parts );
	}

	private static function set_url_params( $url_parts ) {
		$f = function ( $key ) use ( $url_parts ) {
			return \EE\Utils\get_flag_value( $url_parts, $key, '' );
		};

		if ( isset( $url_parts['host'] ) ) {
			if ( isset( $url_parts['scheme'] ) && 'https' === strtolower( $url_parts['scheme'] ) ) {
				$_SERVER['HTTPS'] = 'on';
			}

			$_SERVER['HTTP_HOST'] = $url_parts['host'];
			if ( isset( $url_parts['port'] ) ) {
				$_SERVER['HTTP_HOST'] .= ':' . $url_parts['port'];
			}

			$_SERVER['SERVER_NAME'] = $url_parts['host'];
		}

		$_SERVER['REQUEST_URI']  = $f( 'path' ) . ( isset( $url_parts['query'] ) ? '?' . $url_parts['query'] : '' );
		$_SERVER['SERVER_PORT']  = \EE\Utils\get_flag_value( $url_parts, 'port', '80' );
		$_SERVER['QUERY_STRING'] = $f( 'query' );
	}

	public static function colorize( $string ) {
		return \cli\Colors::colorize( $string, self::get_runner()->in_color() );
	}

	/**
	 * Schedule a callback to be executed at a certain point.
	 */
	public static function add_hook( $when, $callback ) {
		if ( in_array( $when, self::$hooks_passed ) ) {
			call_user_func( $callback );
		}

		self::$hooks[ $when ][] = $callback;
	}

	/**
	 * Execute registered callbacks.
	 */
	public static function do_hook( $when ) {
		self::$hooks_passed[] = $when;

		if ( ! isset( self::$hooks[ $when ] ) ) {
			return;
		}

		foreach ( self::$hooks[ $when ] as $callback ) {
			call_user_func( $callback );
		}
	}

	/**
	 * Add a command to the ee-cli list of commands
	 *
	 * @param string $name The name of the command that will be used in the CLI
	 * @param string $callable The command implementation as a class, function or closure
	 * @param array  $args An associative array with additional parameters:
	 *   'before_invoke' => callback to execute before invoking the command,
	 *   'shortdesc' => short description (80 char or less) for the command,
	 *   'synopsis' => the synopsis for the command (string or array)
	 *   'when' => execute callback on a named ee-cli hook (e.g. before_wp_load)
	 */
	public static function add_command( $name, $callable, $args = array() ) {
		$valid = false;
		if ( is_object( $callable ) && ( $callable instanceof \Closure ) ) {
			$valid = true;
		} else if ( is_string( $callable ) && function_exists( $callable ) ) {
			$valid = true;
		} else if ( is_string( $callable ) && class_exists( (string) $callable ) ) {
			$valid = true;
		} else if ( is_object( $callable ) ) {
			$valid = true;
		} else if ( is_array( $callable ) && is_callable( $callable ) ) {
			$valid = true;
		}
		if ( ! $valid ) {
			if ( is_array( $callable ) ) {
				$callable[0] = is_object( $callable[0] ) ? get_class( $callable[0] ) : $callable[0];
				$callable    = array( $callable[0], $callable[1] );
			}
			EE::error( sprintf( "Callable %s does not exist, and cannot be registered as `wp %s`.", json_encode( $callable ), $name ) );
		}

		if ( isset( $args['before_invoke'] ) ) {
			self::add_hook( "before_invoke:$name", $args['before_invoke'] );
		}

		$path = preg_split( '/\s+/', $name );

		$leaf_name = array_pop( $path );
		$full_path = $path;

		$command = self::get_root_command();

		while ( ! empty( $path ) ) {
			$subcommand_name = $path[0];
			$subcommand      = $command->find_subcommand( $path );

			// create an empty container
			if ( ! $subcommand ) {
				$subcommand = new Dispatcher\CompositeCommand( $command, $subcommand_name, new \EE\DocParser( '' ) );
				$command->add_subcommand( $subcommand_name, $subcommand );
			}

			$command = $subcommand;
		}

		$leaf_command = Dispatcher\CommandFactory::create( $leaf_name, $callable, $command );

		if ( ! $command->can_have_subcommands() ) {
			throw new Exception( sprintf( "'%s' can't have subcommands.", implode( ' ', Dispatcher\get_path( $command ) ) ) );
		}

		if ( isset( $args['shortdesc'] ) ) {
			$leaf_command->set_shortdesc( $args['shortdesc'] );
		}

		if ( isset( $args['synopsis'] ) ) {
			if ( is_string( $args['synopsis'] ) ) {
				$leaf_command->set_synopsis( $args['synopsis'] );
			} else if ( is_array( $args['synopsis'] ) ) {
				$leaf_command->set_synopsis( \EE\SynopsisParser::render( $args['synopsis'] ) );
			}
		}

		if ( isset( $args['when'] ) ) {
			self::get_runner()->register_early_invoke( $args['when'], $leaf_command );
		}

		$command->add_subcommand( $leaf_name, $leaf_command );
	}

	/**
	 * Display a message in the CLI and end with a newline
	 *
	 * @param string $message
	 */
	public static function line( $message = '' ) {
		echo $message . "\n";
	}

	/**
	 * Log an informational message.
	 *
	 * @param string $message
	 */
	public static function log( $message ) {
		self::$logger->log( $message );
	}

	/**
	 * Log an informational message.
	 *
	 * @param string $message
	 */
	public static function info( $message ) {
		self::$logger->info( $message );
	}

	/**
	 * Display a success in the CLI and end with a newline
	 *
	 * @param string $message
	 */
	public static function success( $message ) {
		self::$logger->success( $message );
	}

	/**
	 * Display debug message prefixed with "Debug: " when `--debug` is used.
	 * Log debug information
	 *
	 * @param string $message
	 */
	public static function debug( $message ) {
		self::$logger->debug( self::error_to_string( $message ) );
	}

	/**
	 * Display a warning in the CLI and end with a newline
	 *
	 * @param string $message
	 */
	public static function warning( $message ) {
		self::$logger->warning( self::error_to_string( $message ) );
	}

	/**
	 * Display an error in the CLI and end with a newline
	 *
	 * @param string|WP_Error $message
	 * @param bool            $exit if true, the script will exit()
	 */
	public static function error( $message, $exit = true ) {
		if ( ! isset( self::get_runner()->assoc_args['completions'] ) ) {
			self::$logger->error( self::error_to_string( $message ) );
		}

		if ( $exit ) {
			exit( 1 );
		}
	}

	/**
	 * Display an error in the CLI and end with a newline
	 *
	 * @param array $message each element from the array will be printed on its own line
	 */
	public static function error_multi_line( $message_lines ) {
		if ( ! isset( self::get_runner()->assoc_args['completions'] ) && is_array( $message_lines ) ) {
			self::$logger->error_multi_line( array_map( array( __CLASS__, 'error_to_string' ), $message_lines ) );
		}
	}

	/**
	 * Ask for confirmation before running a destructive operation.
	 */
	public static function confirm( $question, $assoc_args = array() ) {
		if ( ! \EE\Utils\get_flag_value( $assoc_args, 'yes' ) ) {
			fwrite( STDOUT, $question . " [y/n] " );

			$answer = strtolower( trim( fgets( STDIN ) ) );

			if ( 'y' != $answer ) {
				exit;
			}
		}
	}

	/**
	 * @param $message
	 *
	 * @return string
	 */
	public static function input_value( $message ) {
		fwrite( STDOUT, $message );
		$answer = trim( fgets( STDIN ) );

		return $answer;
	}

	public static function input_hidden_value( $message ) {
		fwrite( STDOUT, $message );
		system( 'stty -echo' );
		$answer = trim( fgets( STDIN ) );
		system( 'stty echo' );

		return $answer;
	}

	/**
	 * Read value from a positional argument or from STDIN.
	 *
	 * @param array $args The list of positional arguments.
	 * @param int   $index At which position to check for the value.
	 *
	 * @return string
	 */
	public static function get_value_from_arg_or_stdin( $args, $index ) {
		if ( isset( $args[ $index ] ) ) {
			$raw_value = $args[ $index ];
		} else {
			// We don't use file_get_contents() here because it doesn't handle
			// Ctrl-D properly, when typing in the value interactively.
			$raw_value = '';
			while ( ( $line = fgets( STDIN ) ) !== false ) {
				$raw_value .= $line;
			}
		}

		return $raw_value;
	}

	/**
	 * Read a value, from various formats.
	 *
	 * @param mixed $value
	 * @param array $assoc_args
	 */
	public static function read_value( $raw_value, $assoc_args = array() ) {
		if ( \EE\Utils\get_flag_value( $assoc_args, 'format' ) === 'json' ) {
			$value = json_decode( $raw_value, true );
			if ( null === $value ) {
				EE::error( sprintf( 'Invalid JSON: %s', $raw_value ) );
			}
		} else {
			$value = $raw_value;
		}

		return $value;
	}

	/**
	 * Display a value, in various formats
	 *
	 * @param mixed $value
	 * @param array $assoc_args
	 */
	public static function print_value( $value, $assoc_args = array() ) {
		if ( \EE\Utils\get_flag_value( $assoc_args, 'format' ) === 'json' ) {
			$value = json_encode( $value );
		} elseif ( is_array( $value ) || is_object( $value ) ) {
			$value = var_export( $value );
		}

		echo $value . "\n";
	}

	/**
	 * Convert a wp_error into a string
	 *
	 * @param mixed $errors
	 *
	 * @return string
	 */
	public static function error_to_string( $errors ) {
		if ( is_string( $errors ) ) {
			return $errors;
		}

		if ( is_object( $errors ) && is_a( $errors, 'WP_Error' ) ) {
			foreach ( $errors->get_error_messages() as $message ) {
				if ( $errors->get_error_data() ) {
					return $message . ' ' . json_encode( $errors->get_error_data() );
				} else {
					return $message;
				}
			}
		}
	}

	/**
	 * Launch an external process that takes over I/O.
	 *
	 * @param string Command to call
	 * @param bool Whether to exit if the command returns an error status
	 * @param bool Whether to return an exit status (default) or detailed execution results
	 *
	 * @return int|ProcessRun The command exit status, or a ProcessRun instance
	 */
	public static function launch( $command, $exit_on_error = true, $return_detailed = false, $write_log = false ) {
		$proc    = Process::create( $command );
		$results = $proc->run( $write_log );

		if ( $results->return_code && $exit_on_error ) {
			exit( $results->return_code );
		}

		if ( $return_detailed ) {
			return $results;
		} else {
			return $results->return_code;
		}
	}

	/**
	 * Launch an external process that takes over I/O with message.
	 *
	 * @param string $command Command to call.
	 * @param string $message Message to write in log.
	 * @param bool   $exit_on_error
	 * @param bool   $return_detailed
	 *
	 * @return int|ProcessRun return code of executed command.
	 */
	public static function exec_cmd( $command, $message = '', $exit_on_error = false, $write_log = true, $return_detailed = false ) {
		if ( ! empty( $message ) ) {
			EE::debug( $message );
		}
		$cmd_result = self::launch( $command, $exit_on_error, $return_detailed, $write_log );

		return $cmd_result;
	}

	/**
	 * @param        $command
	 * @param string $message
	 * @param bool   $exit_on_error
	 *
	 * @return string return output of executed command.
	 */
	public static function exec_cmd_output( $command, $message = '', $exit_on_error = false ) {
		Process::write_log( $message );
		$cmd_result      = '';
		$cmd_result_data = self::launch( $command, $exit_on_error, true );
		if ( ! $cmd_result_data->return_code ) {
			if ( $cmd_result_data->stdout ) {
				$cmd_result = $cmd_result_data->stdout;
			}
		}

		return $cmd_result;
	}

    /**
     * Show the tail logs for the given paths
     *
     * @param string $paths separated by spaces
     *
     */
    public static function tail_logs($paths){

        if(empty($paths)){
            self::error("No path specified for tail logging");
            return;
        }
        self::debug("running: tail -f ".$paths);
        self::debug(system("tail -f ".$paths));

    }
	public static function invoke_editor( $filename ) {
		try {
			system( "sensible-editor " . $filename ." >/dev/tty" );
		} catch ( Exception $e ) {
			EE::debug( $e->getMessage() );
			EE::log("Failed invoke editor");
		}
	}

	/**
	 * Launch another ee-cli command using the runtime arguments for the current process
	 *
	 * @param       string Command to call
	 * @param array $args Positional arguments to use
	 * @param array $assoc_args Associative arguments to use
	 * @param       bool Whether to exit if the command returns an error status
	 * @param       bool Whether to return an exit status (default) or detailed execution results
	 * @param array $runtime_args Override one or more global args (path,url,user,allow-root)
	 *
	 * @return int|ProcessRun The command exit status, or a ProcessRun instance
	 */
	public static function launch_self( $command, $args = array(), $assoc_args = array(), $exit_on_error = true, $return_detailed = false, $runtime_args = array() ) {
		$reused_runtime_args = array(
			'path',
			'url',
			'user',
			'allow-root',
		);

		foreach ( $reused_runtime_args as $key ) {
			if ( isset( $runtime_args[ $key ] ) ) {
				$assoc_args[ $key ] = $runtime_args[ $key ];
			} else if ( $value = self::get_runner()->config[ $key ] ) {
				$assoc_args[ $key ] = $value;
			}
		}

		$php_bin = self::get_php_binary();

		$script_path = $GLOBALS['argv'][0];

		$args       = implode( ' ', array_map( 'escapeshellarg', $args ) );
		$assoc_args = \EE\Utils\assoc_args_to_str( $assoc_args );

		$full_command = "{$php_bin} {$script_path} {$command} {$args} {$assoc_args}";

		return self::launch( $full_command, $exit_on_error, $return_detailed );
	}

	/**
	 * Get the path to the PHP binary used when executing ee-cli.
	 * Environment values permit specific binaries to be indicated.
	 *
	 * @return string
	 */
	public static function get_php_binary() {
		if ( defined( 'PHP_BINARY' ) ) {
			return PHP_BINARY;
		}

		if ( getenv( 'EE_PHP_USED' ) ) {
			return getenv( 'EE_PHP_USED' );
		}

		if ( getenv( 'EE_PHP' ) ) {
			return getenv( 'EE_PHP' );
		}

		return 'php';
	}

	public static function get_config( $key = null ) {
		if ( null === $key ) {
			return self::get_runner()->config;
		}

		if ( ! isset( self::get_runner()->config[ $key ] ) ) {
			self::warning( "Unknown config option '$key'." );

			return null;
		}

		return self::get_runner()->config[ $key ];
	}

	/**
	 * Run a given command within the current process using the same global parameters.
	 *
	 * To run a command using a new process with the same global parameters, use EE::launch_self()
	 * To run a command using a new process with different global parameters, use EE::launch()
	 *
	 * @param array
	 * @param array
	 */
	public static function run_command( $args, $assoc_args = array() ) {
		self::get_runner()->run_command( $args, $assoc_args );
	}

	// DEPRECATED STUFF

	public static function add_man_dir() {
		trigger_error( 'EE::add_man_dir() is deprecated. Add docs inline.', E_USER_WARNING );
	}

	// back-compat
	public static function out( $str ) {
		fwrite( STDOUT, $str );
	}

	// back-compat
	public static function addCommand( $name, $class ) {
		trigger_error( sprintf( 'wp %s: %s is deprecated. use EE_CLI::add_command() instead.', $name, __FUNCTION__ ), E_USER_WARNING );
		self::add_command( $name, $class );
	}
}

