<?php

use \EE\Utils;
use \EE\Dispatcher;
use cli\Shell;

class Help_Command extends EE_Command {

	/**
	 * Get help on EE, or on a specific command.
	 *
	 * ## OPTIONS
	 *
	 * [<command>...]
	 * : Get help on a specific command.
	 *
	 * ## EXAMPLES
	 *
	 *     # get help for `core` command
	 *     ee help core
	 *
	 *     # get help for `core download` subcommand
	 *     ee help core download
	 */
	public function __invoke( $args, $assoc_args ) {
		$r = EE::get_runner()->find_command_to_run( $args );

		if ( is_array( $r ) ) {
			list( $command ) = $r;

			self::show_help( $command );
			exit;
		}
	}

	private static function show_help( $command ) {
		$out = self::get_initial_markdown( $command );

		// Remove subcommands if in columns - will wordwrap separately.
		$subcommands = '';
		$column_subpattern = '[ \t]+[^\t]+\t+';
		if ( preg_match( '/(^## SUBCOMMANDS[^\n]*\n+' . $column_subpattern . '.+?)(?:^##|\z)/ms', $out, $matches, PREG_OFFSET_CAPTURE ) ) {
			$subcommands = $matches[1][0];
			$subcommands_header = "## SUBCOMMANDS\n";
			$out = substr_replace( $out, $subcommands_header, $matches[1][1], strlen( $subcommands ) );
		}

		$out .= self::parse_reference_links( $command->get_longdesc() );

		// definition lists
		$out = preg_replace_callback( '/([^\n]+)\n: (.+?)(\n\n|$)/s', array( __CLASS__, 'rewrap_param_desc' ), $out );

		// Ensure lines with no leading whitespace that aren't section headers are indented.
		$out = preg_replace( '/^((?! |\t|##).)/m', "\t$1", $out );

		$tab = str_repeat( ' ', 2 );

		// Need to de-tab for wordwrapping to work properly.
		$out = str_replace( "\t", $tab, $out );

		$wordwrap_width = Shell::columns();

		// Wordwrap with indent.
		$out = preg_replace_callback(
			'/^( *)([^\n]+)\n/m',
			function ( $matches ) use ( $wordwrap_width ) {
				return $matches[1] . str_replace( "\n", "\n{$matches[1]}", wordwrap( $matches[2], $wordwrap_width - strlen( $matches[1] ) ) ) . "\n";
			},
			$out
		);

		if ( $subcommands ) {
			// Wordwrap with column indent.
			$subcommands = preg_replace_callback(
				'/^(' . $column_subpattern . ')([^\n]+)\n/m',
				function ( $matches ) use ( $wordwrap_width, $tab ) {
					// Need to de-tab for wordwrapping to work properly.
					$matches[1] = str_replace( "\t", $tab, $matches[1] );
					$matches[2] = str_replace( "\t", $tab, $matches[2] );
					$padding_len = strlen( $matches[1] );
					$padding = str_repeat( ' ', $padding_len );
					return $matches[1] . str_replace( "\n", "\n$padding", wordwrap( $matches[2], $wordwrap_width - $padding_len ) ) . "\n";
				},
				$subcommands
			);

			// Put subcommands back.
			$out = str_replace( $subcommands_header, $subcommands, $out );
		}

		// section headers
		$out = preg_replace( '/^## ([A-Z ]+)/m', EE::colorize( '%9\1%n' ), $out );

		self::pass_through_pager( $out );
	}

	private static function rewrap_param_desc( $matches ) {
		$param = $matches[1];
		$desc = self::indent( "\t\t", $matches[2] );
		return "\t$param\n$desc\n\n";
	}

	private static function indent( $whitespace, $text ) {
		$lines = explode( "\n", $text );
		foreach ( $lines as &$line ) {
			$line = $whitespace . $line;
		}
		return implode( $lines, "\n" );
	}

	private static function pass_through_pager( $out ) {

		if ( ! Utils\check_proc_available( null /*context*/, true /*return*/ ) ) {
			EE::debug( 'Warning: check_proc_available() failed in pass_through_pager().', 'help' );
			return $out;
		}

		if ( false === ( $pager = getenv( 'PAGER' ) ) ) {
			$pager = Utils\is_windows() ? 'more' : 'less -r';
		}

		// For Windows 7 need to set code page to something other than Unicode (65001) to get around "Not enough memory." error with `more.com` on PHP 7.1+.
		if ( 'more' === $pager && defined( 'PHP_WINDOWS_VERSION_MAJOR' ) && PHP_WINDOWS_VERSION_MAJOR < 10 && function_exists( 'sapi_windows_cp_set' ) ) {
			// Note will also apply to Windows 8 (see http://msdn.microsoft.com/en-us/library/windows/desktop/ms724832.aspx) but probably harmless anyway.
			$cp = getenv( 'EE_WINDOWS_CODE_PAGE' ) ?: 1252; // Code page 1252 is the most used so probably the most compat.
			sapi_windows_cp_set( $cp ); // `sapi_windows_cp_set()` introduced PHP 7.1.
		}

		// convert string to file handle
		$fd = fopen( 'php://temp', 'r+b' );
		fwrite( $fd, $out );
		rewind( $fd );

		$descriptorspec = array(
			0 => $fd,
			1 => STDOUT,
			2 => STDERR,
		);

		return proc_close( Utils\proc_open_compat( $pager, $descriptorspec, $pipes ) );
	}

	private static function get_initial_markdown( $command ) {
		$name = implode( ' ', Dispatcher\get_path( $command ) );

		$binding = array(
			'name' => $name,
			'shortdesc' => $command->get_shortdesc(),
		);

		$binding['synopsis'] = "$name " . $command->get_synopsis();

		$alias = $command->get_alias();
		if ( $alias ) {
			$binding['alias'] = $alias;
		}

		if ( $command->can_have_subcommands() ) {
			$binding['has-subcommands']['subcommands'] = self::render_subcommands( $command );
		}

		return Utils\mustache_render( 'man.mustache', $binding );
	}

	private static function render_subcommands( $command ) {
		$subcommands = array();
		foreach ( $command->get_subcommands() as $subcommand ) {

			if ( EE::get_runner()->is_command_disabled( $subcommand ) ) {
				continue;
			}

			$subcommands[ $subcommand->get_name() ] = $subcommand->get_shortdesc();
		}

		$max_len = self::get_max_len( array_keys( $subcommands ) );

		$lines = array();
		foreach ( $subcommands as $name => $desc ) {
			$lines[] = str_pad( $name, $max_len ) . "\t\t\t" . $desc;
		}

		return $lines;
	}

	private static function get_max_len( $strings ) {
		$max_len = 0;
		foreach ( $strings as $str ) {
			$len = strlen( $str );
			if ( $len > $max_len ) {
				$max_len = $len;
			}
		}

		return $max_len;
	}

	/**
	 * Parse reference links from longdescription.
	 *
	 * @param  string $longdesc The longdescription from the `$command->get_longdesc()`.
	 * @return string The longdescription which has links as footnote.
	 */
	private static function parse_reference_links( $longdesc ) {
		$description = '';
		foreach ( explode( "\n", $longdesc ) as $line ) {
			if ( 0 === strpos( $line, '#' ) ) {
				break;
			}
			$description .= $line . "\n";
		}

		// Fires if it has description text at the head of `$longdesc`.
		if ( $description ) {
			$links = array(); // An array of URLs from the description.
			$pattern = '/\[.+?\]\((https?:\/\/.+?)\)/';
			$newdesc = preg_replace_callback( $pattern, function( $matches ) use ( &$links ) {
				static $count = 0;
				$count++;
				$links[] = $matches[1];
				return str_replace( '(' . $matches[1] . ')', '[' . $count . ']', $matches[0] );
			}, $description );

			$footnote = '';
			for ( $i = 0; $i < count( $links ); $i++ ) {
				$n = $i + 1;
				$footnote .= '[' . $n . '] ' . $links[ $i ] . "\n";
			}

			if ( $footnote ) {
				$newdesc = trim( $newdesc ) . "\n\n---\n" . $footnote;
				$longdesc = str_replace( trim( $description ), trim( $newdesc ), $longdesc );
			}
		}

		return $longdesc;
	}
}

EE::add_command( 'help', 'Help_Command' );

