<?php

namespace EE\Loggers;

/**
 * Default logger for success, warning, error, and standard messages.
 */
class Regular extends Base {

	/**
	 * Whether info and success messages go to STDERR.
	 *
	 * @var bool
	 */
	private $info_to_stderr = false;

	/**
	 * @param bool $in_color Whether or not to Colorize strings.
	 */
	public function __construct( $in_color ) {
		$this->in_color = $in_color;
	}

	/**
	 * Write info and success messages to STDERR instead of STDOUT, e.g. while STDOUT carries a command's data.
	 *
	 * @param bool $enable Whether to write them to STDERR.
	 */
	public function info_to_stderr( $enable ) {
		$this->info_to_stderr = (bool) $enable;
	}

	/**
	 * Write an informational message to STDOUT, or STDERR after info_to_stderr( true ).
	 *
	 * @param string $message Message to write.
	 */
	public function info( $message ) {
		$this->write( $this->info_to_stderr ? STDERR : STDOUT, $message . "\n" );
	}

	/**
	 * Write a success message, prefixed with "Success: ".
	 *
	 * @param string $message Message to write.
	 */
	public function success( $message ) {
		$this->_line( $message, 'Success', '%G', $this->info_to_stderr ? STDERR : STDOUT );
	}

	/**
	 * Write a warning message to STDERR, prefixed with "Warning: ".
	 *
	 * @param string $message Message to write.
	 */
	public function warning( $message ) {
		$this->_line( $message, 'Warning', '%C', STDERR );
	}

	/**
	 * Write an message to STDERR, prefixed with "Error: ".
	 *
	 * @param string $message Message to write.
	 */
	public function error( $message ) {
		$this->_line( $message, 'Error', '%R', STDERR );
	}

	/**
	 * Similar to error( $message ), but outputs $message in a red box
	 *
	 * @param  array $message Message to write.
	 */
	public function error_multi_line( $message_lines ) {
		// convert tabs to four spaces, as some shells will output the tabs as variable-length
		$message_lines = array_map(
			function( $line ) {
				return str_replace( "\t", '    ', $line );
			},
			$message_lines
		);

		$longest = max( array_map( 'strlen', $message_lines ) );

		// write an empty line before the message
		$empty_line = \cli\Colors::colorize( '%w%1 ' . str_repeat( ' ', $longest ) . ' %n' );
		$this->write( STDERR, "\n\t$empty_line\n" );

		foreach ( $message_lines as $line ) {
			$padding = str_repeat( ' ', $longest - strlen( $line ) );
			$line = \cli\Colors::colorize( "%w%1 $line $padding%n" );
			$this->write( STDERR, "\t$line\n" );
		}

		// write an empty line after the message
		$this->write( STDERR, "\t$empty_line\n\n" );
	}
}
