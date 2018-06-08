<?php

use \EE\ExitException;
use \EE\Dispatcher;
use \EE\FileCache;
use \EE\Process;
use \EE\Utils;
use Mustangostang\Spyc;

/**
 * Various utilities for EE commands.
 */
class EE {

	private static $configurator;

	private static $logger;

	private static $hooks = array(), $hooks_passed = array();

	private static $capture_exit = false;

	private static $deferred_additions = array();

	private static $db;

	private static $docker;

	private static $file_logger;

	/**
	 * Set the logger instance.
	 *
	 * @param object $logger
	 */
	public static function set_logger( $logger ) {
		self::$logger = $logger;
	}

	/**
	 * Set the file logger instance.
	 *
	 * @param object $file_logger
	 */
	public static function set_file_logger( $file_logger ) {
		self::$file_logger = $file_logger;
	}

	/**
	 * Return file logger object.
	 *
	 * @return object $file_logger
	 */
	public static function get_file_logger() {
		return self::$file_logger;
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
	 * @return FileCache
	 */
	public static function get_cache() {
		static $cache;

		if ( ! $cache ) {
			$home = Utils\get_home_dir();
			$dir = getenv( 'EE_CACHE_DIR' ) ? : "$home/.ee/cache";

			// 6 months, 300mb
			$cache = new FileCache( $dir, 15552000, 314572800 );

			// clean older files on shutdown with 1/50 probability
			if ( 0 === mt_rand( 0, 50 ) ) {
				register_shutdown_function(
					function () use ( $cache ) {
						$cache->clean();
					}
				);
			}
		}

		return $cache;
	}

	/**
	 * Colorize a string for output.
	 *
	 * Yes, you too can change the color of command line text. For instance,
	 * here's how `EE::success()` colorizes "Success: "
	 *
	 * ```
	 * EE::colorize( "%GSuccess:%n " )
	 * ```
	 *
	 * Uses `\cli\Colors::colorize()` to transform color tokens to display
	 * settings. Choose from the following tokens (and note 'reset'):
	 *
	 * * %y => ['color' => 'yellow'],
	 * * %g => ['color' => 'green'],
	 * * %b => ['color' => 'blue'],
	 * * %r => ['color' => 'red'],
	 * * %p => ['color' => 'magenta'],
	 * * %m => ['color' => 'magenta'],
	 * * %c => ['color' => 'cyan'],
	 * * %w => ['color' => 'grey'],
	 * * %k => ['color' => 'black'],
	 * * %n => ['color' => 'reset'],
	 * * %Y => ['color' => 'yellow', 'style' => 'bright'],
	 * * %G => ['color' => 'green', 'style' => 'bright'],
	 * * %B => ['color' => 'blue', 'style' => 'bright'],
	 * * %R => ['color' => 'red', 'style' => 'bright'],
	 * * %P => ['color' => 'magenta', 'style' => 'bright'],
	 * * %M => ['color' => 'magenta', 'style' => 'bright'],
	 * * %C => ['color' => 'cyan', 'style' => 'bright'],
	 * * %W => ['color' => 'grey', 'style' => 'bright'],
	 * * %K => ['color' => 'black', 'style' => 'bright'],
	 * * %N => ['color' => 'reset', 'style' => 'bright'],
	 * * %3 => ['background' => 'yellow'],
	 * * %2 => ['background' => 'green'],
	 * * %4 => ['background' => 'blue'],
	 * * %1 => ['background' => 'red'],
	 * * %5 => ['background' => 'magenta'],
	 * * %6 => ['background' => 'cyan'],
	 * * %7 => ['background' => 'grey'],
	 * * %0 => ['background' => 'black'],
	 * * %F => ['style' => 'blink'],
	 * * %U => ['style' => 'underline'],
	 * * %8 => ['style' => 'inverse'],
	 * * %9 => ['style' => 'bright'],
	 * * %_ => ['style' => 'bright']
	 *
	 * @access public
	 * @category Output
	 *
	 * @param string $string String to colorize for output, with color tokens.
	 * @return string Colorized string.
	 */
	public static function colorize( $string ) {
		return \cli\Colors::colorize( $string, self::get_runner()->in_color() );
	}

	/**
	 * Schedule a callback to be executed at a certain point.
	 *
	 * Hooks conceptually are very similar to WordPress actions. EE hooks
	 * are typically called before WordPress is loaded.
	 *
	 * EE hooks include:
	 *
	 * * `before_add_command:<command>` - Before the command is added.
	 * * `after_add_command:<command>` - After the command was added.
	 * * `before_invoke:<command>` - Just before a command is invoked.
	 * * `after_invoke:<command>` - Just after a command is invoked.
	 * * `find_command_to_run_pre` - Just before EE finds the command to run.
	 *
	 * EE commands can create their own hooks with `EE::do_hook()`.
	 *
	 * If additional arguments are passed through the `EE::do_hook()` call,
	 * these will be passed on to the callback provided by `EE::add_hook()`.
	 *
	 *
	 * @access public
	 * @category Registration
	 *
	 * @param string $when Identifier for the hook.
	 * @param mixed $callback Callback to execute when hook is called.
	 * @return null
	 */
	public static function add_hook( $when, $callback ) {
		if ( array_key_exists( $when, self::$hooks_passed ) ) {
			call_user_func_array( $callback, (array) self::$hooks_passed[ $when ] );
		}

		self::$hooks[ $when ][] = $callback;
	}

	/**
	 * Execute callbacks registered to a given hook.
	 *
	 * See `EE::add_hook()` for details on EE's internal hook system.
	 * Commands can provide and call their own hooks.
	 *
	 * @access public
	 * @category Registration
	 *
	 * @param string $when Identifier for the hook.
	 * @param mixed ... Optional. Arguments that will be passed onto the
	 *                  callback provided by `EE::add_hook()`.
	 * @return null
	 */
	public static function do_hook( $when ) {
		$args = func_num_args() > 1
			? array_slice( func_get_args(), 1 )
			: array();

		self::$hooks_passed[ $when ] = $args;

		if ( ! isset( self::$hooks[ $when ] ) ) {
			return;
		}

		foreach ( self::$hooks[ $when ] as $callback ) {
			call_user_func_array( $callback, $args );
		}
	}

	/**
	 * Register a command to EE.
	 *
	 * EE supports using any callable class, function, or closure as a
	 * command. `EE::add_command()` is used for both internal and
	 * third-party command registration.
	 *
	 * Command arguments are parsed from PHPDoc by default, but also can be
	 * supplied as an optional third argument during registration.
	 *
	 * ```
	 * # Register a custom 'foo' command to output a supplied positional param.
	 * #
	 * # $ ee foo bar --append=qux
	 * # Success: bar qux
	 *
	 * /**
	 *  * My awesome closure command
	 *  *
	 *  * <message>
	 *  * : An awesome message to display
	 *  *
	 *  * --append=<message>
	 *  * : An awesome message to append to the original message.
	 *  *\/
	 * $foo = function( $args, $assoc_args ) {
	 *     EE::success( $args[0] . ' ' . $assoc_args['append'] );
	 * };
	 * EE::add_command( 'foo', $foo );
	 * ```
	 *
	 * @access public
	 * @category Registration
	 *
	 * @param string   $name Name for the command (e.g. "post list" or "site empty").
	 * @param callable $callable Command implementation as a class, function or closure.
	 * @param array    $args {
	 *    Optional. An associative array with additional registration parameters.
	 *
	 *    @type callable $before_invoke Callback to execute before invoking the command.
	 *    @type callable $after_invoke  Callback to execute after invoking the command.
	 *    @type string   $shortdesc     Short description (80 char or less) for the command.
	 *    @type string   $longdesc      Description of arbitrary length for examples, etc.
	 *    @type string   $synopsis      The synopsis for the command (string or array).
	 *    @type string   $when          Execute callback on a named EE hook.
	 *    @type bool     $is_deferred   Whether the command addition had already been deferred.
	 * }
	 * @return true True on success, false if deferred, hard error if registration failed.
	 */
	public static function add_command( $name, $callable, $args = array() ) {
		// Bail immediately if the EE executable has not been run.
		if ( ! defined( 'EE' ) ) {
			return false;
		}

		$valid = false;
		if ( is_callable( $callable ) ) {
			$valid = true;
		} elseif ( is_string( $callable ) && class_exists( (string) $callable ) ) {
			$valid = true;
		} elseif ( is_object( $callable ) ) {
			$valid = true;
		}
		if ( ! $valid ) {
			if ( is_array( $callable ) ) {
				$callable[0] = is_object( $callable[0] ) ? get_class( $callable[0] ) : $callable[0];
				$callable = array( $callable[0], $callable[1] );
			}
			EE::error( sprintf( 'Callable %s does not exist, and cannot be registered as `ee %s`.', json_encode( $callable ), $name ) );
		}

		$addition = new Dispatcher\CommandAddition();
		self::do_hook( "before_add_command:{$name}", $addition );

		if ( $addition->was_aborted() ) {
			EE::warning( "Aborting the addition of the command '{$name}' with reason: {$addition->get_reason()}." );
			return false;
		}

		foreach ( array( 'before_invoke', 'after_invoke' ) as $when ) {
			if ( isset( $args[ $when ] ) ) {
				self::add_hook( "{$when}:{$name}", $args[ $when ] );
			}
		}

		$path = preg_split( '/\s+/', $name );

		$leaf_name = array_pop( $path );
		$full_path = $path;

		$command = self::get_root_command();

		while ( ! empty( $path ) ) {
			$subcommand_name = $path[0];
			$parent = implode( ' ', $path );
			$subcommand = $command->find_subcommand( $path );

			// Parent not found. Defer addition or create an empty container as
			// needed.
			if ( ! $subcommand ) {
				if ( isset( $args['is_deferred'] ) && $args['is_deferred'] ) {
					$subcommand = new Dispatcher\CompositeCommand(
						$command,
						$subcommand_name,
						new \EE\DocParser( '' )
					);
					$command->add_subcommand( $subcommand_name, $subcommand );
				} else {
					self::defer_command_addition(
						$name,
						$parent,
						$callable,
						$args
					);

					return false;
				}
			}

			$command = $subcommand;
		}

		$leaf_command = Dispatcher\CommandFactory::create( $leaf_name, $callable, $command );

		if ( $leaf_command instanceof Dispatcher\CommandNamespace && array_key_exists( $leaf_name, $command->get_subcommands() ) ) {
			return false;
		}

		if ( ! $command->can_have_subcommands() ) {
			throw new Exception(
				sprintf(
					"'%s' can't have subcommands.",
					implode( ' ' , Dispatcher\get_path( $command ) )
				)
			);
		}

		if ( isset( $args['shortdesc'] ) ) {
			$leaf_command->set_shortdesc( $args['shortdesc'] );
		}

		if ( isset( $args['longdesc'] ) ) {
			$leaf_command->set_longdesc( $args['longdesc'] );
		}

		if ( isset( $args['synopsis'] ) ) {
			if ( is_string( $args['synopsis'] ) ) {
				$leaf_command->set_synopsis( $args['synopsis'] );
			} elseif ( is_array( $args['synopsis'] ) ) {
				$synopsis = \EE\SynopsisParser::render( $args['synopsis'] );
				$leaf_command->set_synopsis( $synopsis );
				$long_desc = '';
				$bits = explode( ' ', $synopsis );
				foreach ( $args['synopsis'] as $key => $arg ) {
					$long_desc .= $bits[ $key ] . "\n";
					if ( ! empty( $arg['description'] ) ) {
						$long_desc .= ': ' . $arg['description'] . "\n";
					}
					$yamlify = array();
					foreach ( array( 'default', 'options' ) as $key ) {
						if ( isset( $arg[ $key ] ) ) {
							$yamlify[ $key ] = $arg[ $key ];
						}
					}
					if ( ! empty( $yamlify ) ) {
						$long_desc .= Spyc::YAMLDump( $yamlify );
						$long_desc .= '---' . "\n";
					}
					$long_desc .= "\n";
				}
				if ( ! empty( $long_desc ) ) {
					$long_desc = rtrim( $long_desc, "\r\n" );
					$long_desc = '## OPTIONS' . "\n\n" . $long_desc;
					if ( ! empty( $args['longdesc'] ) ) {
						$long_desc .= "\n\n" . ltrim( $args['longdesc'], "\r\n" );
					}
					$leaf_command->set_longdesc( $long_desc );
				}
			}
		}

		if ( isset( $args['when'] ) ) {
			self::get_runner()->register_early_invoke( $args['when'], $leaf_command );
		}

		$command->add_subcommand( $leaf_name, $leaf_command );

		self::do_hook( "after_add_command:{$name}" );
		return true;
	}

	/**
	 * Defer command addition for a sub-command if the parent command is not yet
	 * registered.
	 *
	 * @param string $name     Name for the sub-command.
	 * @param string $parent   Name for the parent command.
	 * @param string $callable Command implementation as a class, function or closure.
	 * @param array  $args     Optional. See `EE::add_command()` for details.
	 */
	private static function defer_command_addition( $name, $parent, $callable, $args = array() ) {
		$args['is_deferred'] = true;
		self::$deferred_additions[ $name ] = array(
			'parent'   => $parent,
			'callable' => $callable,
			'args'     => $args,
		);
		self::add_hook(
			"after_add_command:$parent",
			function () use ( $name ) {
				$deferred_additions = EE::get_deferred_additions();

				if ( ! array_key_exists( $name, $deferred_additions ) ) {
					return;
				}

				$callable = $deferred_additions[ $name ]['callable'];
				$args     = $deferred_additions[ $name ]['args'];
				EE::remove_deferred_addition( $name );

				EE::add_command( $name, $callable, $args );
			}
		);
	}

	/**
	 * Get the list of outstanding deferred command additions.
	 *
	 * @return array Array of outstanding command additions.
	 */
	public static function get_deferred_additions() {
		return self::$deferred_additions;
	}

	/**
	 * Remove a command addition from the list of outstanding deferred additions.
	 */
	public static function remove_deferred_addition( $name ) {
		if ( ! array_key_exists( $name, self::$deferred_additions ) ) {
			EE::warning( "Trying to remove a non-existent command addition '{$name}'." );
		}

		unset( self::$deferred_additions[ $name ] );
	}

	/**
	 * Display informational message without prefix, and ignore `--quiet`.
	 *
	 * Message is written to STDOUT. `EE::log()` is typically recommended;
	 * `EE::line()` is included for historical compat.
	 *
	 * @access public
	 * @category Output
	 *
	 * @param string $message Message to display to the end user.
	 * @return null
	 */
	public static function line( $message = '' ) {
		echo $message . "\n";
	}

	/**
	 * Display informational message without prefix.
	 *
	 * Message is written to STDOUT, or discarded when `--quiet` flag is supplied.
	 *
	 * ```
	 * # `ee cli update` lets user know of each step in the update process.
	 * EE::log( sprintf( 'Downloading from %s...', $download_url ) );
	 * ```
	 *
	 * @access public
	 * @category Output
	 *
	 * @param string $message Message to write to STDOUT.
	 */
	public static function log( $message ) {
		self::$logger->info( $message );
		self::$file_logger->info( $message );
	}

	/**
	 * Display success message prefixed with "Success: ".
	 *
	 * Success message is written to STDOUT.
	 *
	 * Typically recommended to inform user of successful script conclusion.
	 *
	 * @access public
	 * @category Output
	 *
	 * @param string $message Message to write to STDOUT.
	 * @return null
	 */
	public static function success( $message ) {
		self::$logger->success( $message );
	}

	/**
	 * Display debug message prefixed with "Debug: " when `--debug` is used.
	 *
	 * Debug message is written to STDERR, and includes script execution time.
	 *
	 * Helpful for optionally showing greater detail when needed. Used throughout
	 * EE bootstrap process for easier debugging and profiling.
	 *
	 * @access public
	 * @category Output
	 *
	 * @param string $message Message to write to STDERR.
	 * @param string $group Organize debug message to a specific group.
	 * @return null
	 */
	public static function debug( $message, $group = false ) {
		self::$logger->debug( self::error_to_string( $message ), $group );
		self::$file_logger->debug( $message );
	}

	/**
	 * Display warning message prefixed with "Warning: ".
	 *
	 * Warning message is written to STDERR.
	 *
	 * Use instead of `EE::debug()` when script execution should be permitted
	 * to continue.
	 *
	 * @access public
	 * @category Output
	 *
	 * @param string $message Message to write to STDERR.
	 * @return null
	 */
	public static function warning( $message ) {
		self::$logger->warning( self::error_to_string( $message ) );
		self::$file_logger->warning( $message );
	}

	/**
	 * Display error message prefixed with "Error: " and exit script.
	 *
	 * Error message is written to STDERR. Defaults to halting script execution
	 * with return code 1.
	 *
	 * Use `EE::warning()` instead when script execution should be permitted
	 * to continue.
	 *
	 * @access public
	 * @category Output
	 *
	 * @param string|EE_Error  $message Message to write to STDERR.
	 * @param boolean|integer  $exit    True defaults to exit(1).
	 * @return null
	 */
	public static function error( $message, $exit = true ) {
		if ( ! isset( self::get_runner()->assoc_args['completions'] ) ) {
			self::$logger->error( self::error_to_string( $message ) );
			self::$file_logger->error( $message );
		}

		$return_code = false;
		if ( true === $exit ) {
			$return_code = 1;
		} elseif ( is_int( $exit ) && $exit >= 1 ) {
			$return_code = $exit;
		}

		if ( $return_code ) {
			if ( self::$capture_exit ) {
				throw new ExitException( null, $return_code );
			}
			exit( $return_code );
		}
	}

	/**
	 * Halt script execution with a specific return code.
	 *
	 * Permits script execution to be overloaded by `EE::runcommand()`
	 *
	 * @access public
	 * @category Output
	 *
	 * @param integer $return_code
	 */
	public static function halt( $return_code ) {
		if ( self::$capture_exit ) {
			throw new ExitException( null, $return_code );
		}
		exit( $return_code );
	}

	/**
	 * Display a multi-line error message in a red box. Doesn't exit script.
	 *
	 * Error message is written to STDERR.
	 *
	 * @access public
	 * @category Output
	 *
	 * @param array $message Multi-line error message to be displayed.
	 */
	public static function error_multi_line( $message_lines ) {
		if ( ! isset( self::get_runner()->assoc_args['completions'] ) && is_array( $message_lines ) ) {
			self::$logger->error_multi_line( array_map( array( __CLASS__, 'error_to_string' ), $message_lines ) );
		}
	}

	/**
	 * Ask for confirmation before running a destructive operation.
	 *
	 * If 'y' is provided to the question, the script execution continues and returns true. If
	 * 'n' or any other response is provided to the question, script exits if
	 * $exit is set to true. Otherwise the script returns false.
	 *
	 * @access public
	 * @category Input
	 *
	 * @param string $question Question to display before the prompt.
	 * @param array $assoc_args Skips prompt if 'yes' is provided.
	 */
	public static function confirm( $question, $assoc_args = array(), $exit = true ) {
		if ( ! \EE\Utils\get_flag_value( $assoc_args, 'yes' ) ) {
			fwrite( STDOUT, $question . ' [y/n] ' );

			$answer = strtolower( trim( fgets( STDIN ) ) );

			if ( 'y' != $answer ) {
				if( $exit === true )
					exit;
				return false;
			}
			return true;
		}
		return true;
	}

	/**
	 * Read value from a positional argument or from STDIN.
	 *
	 * @param array $args The list of positional arguments.
	 * @param int $index At which position to check for the value.
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
	 * @access public
	 * @category Input
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
	 * @param mixed $value Value to display.
	 * @param array $assoc_args Arguments passed to the command, determining format.
	 */
	public static function print_value( $value, $assoc_args = array() ) {
		if ( \EE\Utils\get_flag_value( $assoc_args, 'format' ) === 'json' ) {
			$value = json_encode( $value );
		} elseif ( \EE\Utils\get_flag_value( $assoc_args, 'format' ) === 'yaml' ) {
			$value = Spyc::YAMLDump( $value, 2, 0 );
		} elseif ( is_array( $value ) || is_object( $value ) ) {
			$value = var_export( $value );
		}

		echo $value . "\n";
	}

	/**
	 * Convert a EE_error into a string
	 *
	 * @param mixed $errors
	 * @return string
	 */
	public static function error_to_string( $errors ) {
		if ( is_string( $errors ) ) {
			return $errors;
		}

		// Only json_encode() the data when it needs it
		$render_data = function( $data ) {
			if ( is_array( $data ) || is_object( $data ) ) {
				return json_encode( $data );
			}

			return '"' . $data . '"';
		};

		if ( is_object( $errors ) && is_a( $errors, 'EE_Error' ) ) {
			foreach ( $errors->get_error_messages() as $message ) {
				if ( $errors->get_error_data() ) {
					return $message . ' ' . $render_data( $errors->get_error_data() );
				}

				return $message;
			}
		}
	}

	/**
	 * Launch an arbitrary external process that takes over I/O.
	 *
	 * @access public
	 * @category Execution
	 *
	 * @param string $command External process to launch.
	 * @param boolean $exit_on_error Whether to exit if the command returns an elevated return code.
	 * @param boolean $return_detailed Whether to return an exit status (default) or detailed execution results.
	 * @return int|ProcessRun The command exit status, or a ProcessRun object for full details.
	 */
	public static function launch( $command, $exit_on_error = true, $return_detailed = false ) {
		Utils\check_proc_available( 'launch' );

		$proc = Process::create( $command );
		$results = $proc->run();

		if ( -1 == $results->return_code ) {
			self::warning( "Spawned process returned exit code {$results->return_code}, which could be caused by a custom compiled version of PHP that uses the --enable-sigchild option." );
		}

		if ( $results->return_code && $exit_on_error ) {
			exit( $results->return_code );
		}

		if ( $return_detailed ) {
			return $results;
		}

		return $results->return_code;
	}

	/**
	 * Run a EE command in a new process reusing the current runtime arguments.
	 *
	 * Use `EE::runcommand()` instead, which is easier to use and works better.
	 *
	 * Note: While this command does persist a limited set of runtime arguments,
	 * it *does not* persist environment variables. Practically speaking, EE
	 * packages won't be loaded when using EE::launch_self() because the
	 * launched process doesn't have access to the current process $HOME.
	 *
	 * @access public
	 * @category Execution
	 *
	 * @param string $command EE command to call.
	 * @param array $args Positional arguments to include when calling the command.
	 * @param array $assoc_args Associative arguments to include when calling the command.
	 * @param bool $exit_on_error Whether to exit if the command returns an elevated return code.
	 * @param bool $return_detailed Whether to return an exit status (default) or detailed execution results.
	 * @param array $runtime_args Override one or more global args (path,url,user)
	 * @return int|ProcessRun The command exit status, or a ProcessRun instance
	 */
	public static function launch_self( $command, $args = array(), $assoc_args = array(), $exit_on_error = true, $return_detailed = false, $runtime_args = array() ) {
		$reused_runtime_args = array(
			'path',
			'url',
			'user',
		);

		foreach ( $reused_runtime_args as $key ) {
			if ( isset( $runtime_args[ $key ] ) ) {
				$assoc_args[ $key ] = $runtime_args[ $key ];
			} elseif ( $value = self::get_runner()->config[ $key ] ) {
				$assoc_args[ $key ] = $value;
			}
		}

		$php_bin = escapeshellarg( Utils\get_php_binary() );

		$script_path = $GLOBALS['argv'][0];

		if ( getenv( 'EE_CONFIG_PATH' ) ) {
			$config_path = getenv( 'EE_CONFIG_PATH' );
		} else {
			$config_path = EE_CONFIG_PATH . '/config.yml';
		}
		$config_path = escapeshellarg( $config_path );

		$args = implode( ' ', array_map( 'escapeshellarg', $args ) );
		$assoc_args = \EE\Utils\assoc_args_to_str( $assoc_args );

		$full_command = "EE_CONFIG_PATH={$config_path} {$php_bin} {$script_path} {$command} {$args} {$assoc_args}";

		return self::launch( $full_command, $exit_on_error, $return_detailed );
	}

	/**
	 * Get the path to the PHP binary used when executing EE.
	 *
	 * Environment values permit specific binaries to be indicated.
	 *
	 * Note: moved to Utils, left for BC.
	 *
	 * @access public
	 * @category System
	 *
	 * @return string
	 */
	public static function get_php_binary() {
		return Utils\get_php_binary();
	}

	/**
	 * Get values of global configuration parameters.
	 *
	 * Provides access to `--path=<path>`, `--url=<url>`, and other values of
	 * the [global configuration parameters](https://ee.org/config/).
	 *
	 * ```
	 * EE::log( 'The --url=<url> value is: ' . EE::get_config( 'url' ) );
	 * ```
	 *
	 * @access public
	 * @category Input
	 *
	 * @param string $key Get value for a specific global configuration parameter.
	 * @return mixed
	 */
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
	 * Run a EE command.
	 *
	 * Launches a new child process to run a specified EE command.
	 * Optionally:
	 *
	 * * Run the command in an existing process.
	 * * Prevent halting script execution on error.
	 * * Capture and return STDOUT, or full details about command execution.
	 * * Parse JSON output if the command rendered it.
	 *
	 * ```
	 * $options = array(
	 *   'return'     => true,   // Return 'STDOUT'; use 'all' for full object.
	 *   'parse'      => 'json', // Parse captured STDOUT to JSON array.
	 *   'launch'     => false,  // Reuse the current process.
	 *   'exit_error' => true,   // Halt script execution on error.
	 * );
	 * $plugins = EE::runcommand( 'plugin list --format=json', $options );
	 * ```
	 *
	 * @access public
	 * @category Execution
	 *
	 * @param string $command EE command to run, including arguments.
	 * @param array  $options Configuration options for command execution.
	 * @return mixed
	 */
	public static function runcommand( $command, $options = array() ) {
		$defaults = array(
			'launch'     => true, // Launch a new process, or reuse the existing.
			'exit_error' => true, // Exit on error by default.
			'return'     => false, // Capture and return output, or render in realtime.
			'parse'      => false, // Parse returned output as a particular format.
		);
		$options = array_merge( $defaults, $options );
		$launch = $options['launch'];
		$exit_error = $options['exit_error'];
		$return = $options['return'];
		$parse = $options['parse'];
		$retval = null;
		if ( $launch ) {
			Utils\check_proc_available( 'launch option' );

			$descriptors = array(
				0 => STDIN,
				1 => STDOUT,
				2 => STDERR,
			);

			if ( $return ) {
				$descriptors = array(
					0 => STDIN,
					1 => array( 'pipe', 'w' ),
					2 => array( 'pipe', 'w' ),
				);
			}

			$php_bin = escapeshellarg( Utils\get_php_binary() );
			$script_path = $GLOBALS['argv'][0];

			// Persist runtime arguments unless they've been specified otherwise.
			$configurator = \EE::get_configurator();
			$argv = array_slice( $GLOBALS['argv'], 1 );
			list( $_, $_, $runtime_config ) = $configurator->parse_args( $argv );
			foreach ( $runtime_config as $k => $v ) {
				if ( preg_match( "|^--{$k}=?$|", $command ) ) {
					unset( $runtime_config[ $k ] );
				}
			}
			$runtime_config = Utils\assoc_args_to_str( $runtime_config );

			$runcommand = "{$php_bin} {$script_path} {$runtime_config} {$command}";

			$proc = Utils\proc_open_compat( $runcommand, $descriptors, $pipes, getcwd() );

			if ( $return ) {
				$stdout = stream_get_contents( $pipes[1] );
				fclose( $pipes[1] );
				$stderr = stream_get_contents( $pipes[2] );
				fclose( $pipes[2] );
			}
			$return_code = proc_close( $proc );
			if ( -1 == $return_code ) {
				self::warning( 'Spawned process returned exit code -1, which could be caused by a custom compiled version of PHP that uses the --enable-sigchild option.' );
			} elseif ( $return_code && $exit_error ) {
				exit( $return_code );
			}
			if ( true === $return || 'stdout' === $return ) {
				$retval = trim( $stdout );
			} elseif ( 'stderr' === $return ) {
				$retval = trim( $stderr );
			} elseif ( 'return_code' === $return ) {
				$retval = $return_code;
			} elseif ( 'all' === $return ) {
				$retval = (object) array(
					'stdout'      => trim( $stdout ),
					'stderr'      => trim( $stderr ),
					'return_code' => $return_code,
				);
			}
		} else {
			$configurator = self::get_configurator();
			$argv = Utils\parse_str_to_argv( $command );
			list( $args, $assoc_args, $runtime_config ) = $configurator->parse_args( $argv );
			if ( $return ) {
				$existing_logger = self::$logger;
				self::$logger = new EE\Loggers\Execution;
				self::$logger->ob_start();
			}
			if ( ! $exit_error ) {
				self::$capture_exit = true;
			}
			try {
				self::get_runner()->run_command(
					$args, $assoc_args, array(
						'back_compat_conversions' => true,
					)
				);
				$return_code = 0;
			} catch ( ExitException $e ) {
				$return_code = $e->getCode();
			}
			if ( $return ) {
				$execution_logger = self::$logger;
				$execution_logger->ob_end();
				self::$logger = $existing_logger;
				$stdout = $execution_logger->stdout;
				$stderr = $execution_logger->stderr;
				if ( true === $return || 'stdout' === $return ) {
					$retval = trim( $stdout );
				} elseif ( 'stderr' === $return ) {
					$retval = trim( $stderr );
				} elseif ( 'return_code' === $return ) {
					$retval = $return_code;
				} elseif ( 'all' === $return ) {
					$retval = (object) array(
						'stdout'      => trim( $stdout ),
						'stderr'      => trim( $stderr ),
						'return_code' => $return_code,
					);
				}
			}
			if ( ! $exit_error ) {
				self::$capture_exit = false;
			}
		}
		if ( ( true === $return || 'stdout' === $return )
			&& 'json' === $parse ) {
			$retval = json_decode( $retval, true );
		}
		return $retval;
	}

	/**
	 * Run a given command within the current process using the same global
	 * parameters.
	 *
	 * Use `EE::runcommand()` instead, which is easier to use and works better.
	 *
	 * To run a command using a new process with the same global parameters,
	 * use EE::launch_self(). To run a command using a new process with
	 * different global parameters, use EE::launch().
	 *
	 * ```
	 * ob_start();
	 * EE::run_command( array( 'cli', 'cmd-dump' ) );
	 * $ret = ob_get_clean();
	 * ```
	 *
	 * @access public
	 * @category Execution
	 *
	 * @param array $args Positional arguments including command name.
	 * @param array $assoc_args
	 */
	public static function run_command( $args, $assoc_args = array() ) {
		self::get_runner()->run_command( $args, $assoc_args );
	}

	public static function db() {
		if ( empty( self::$db ) ) {
			self::$db = new EE_DB();
		}

		return self::$db;
	}

	public static function docker() {
		if ( empty( self::$docker ) ) {
			self::$docker = new EE_DOCKER();
		}

		return self::$docker;
	}
}
