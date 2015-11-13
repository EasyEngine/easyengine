<?php

namespace EE_CLI;

use EE_CLI;
use EE_CLI\Utils;
use EE_CLI\Dispatcher;

/**
 * Performs the execution of a command.
 *
 * @package EE_CLI
 */
class Runner {

	private $global_config_path, $project_config_path;

	private $config, $extra_config;

	private $arguments, $assoc_args;

	private $_early_invoke = array();

	private $_global_config_path_debug;

	private $_project_config_path_debug;

	public function __get( $key ) {
		if ( '_' === $key[0] )
			return null;

		return $this->$key;
	}

	/**
	 * Register a command for early invocation.
	 *
	 * @param string $when Named execution hook
	 * @param EE_CLI\Dispatcher\Subcommand $command
	 */
	public function register_early_invoke( $when, $command ) {
		$this->_early_invoke[ $when ][] = array_slice( Dispatcher\get_path( $command ), 1 );
	}

	/**
	 * Perform the early invocation of a command.
	 *
	 * @param string $when Named execution hook
	 */
	private function do_early_invoke( $when ) {
		if ( !isset( $this->_early_invoke[ $when ] ) )
			return;

		foreach ( $this->_early_invoke[ $when ] as $path ) {
			if ( $this->cmd_starts_with( $path ) ) {
				$this->_run_command();
				exit;
			}
		}
	}

	/**
	 * Get the path to the global configuration YAML file.
	 *
	 * @return string|false
	 */
	private function get_global_config_path() {

		if ( isset( $runtime_config['config'] ) ) {
			$config_path = $runtime_config['config'];
			$this->_global_config_path_debug = 'Using global config from config runtime arg: ' . $config_path;
		} else if ( getenv( 'EE_CLI_CONFIG_PATH' ) ) {
			$config_path = getenv( 'EE_CLI_CONFIG_PATH' );
			$this->_global_config_path_debug = 'Using global config from EE_CLI_CONFIG_PATH env var: ' . $config_path;
		} else {
			$config_path = getenv( 'HOME' ) . '/.ee-cli/config.yml';
			$this->_global_config_path_debug = 'Using default global config: ' . $config_path;
		}

		if ( is_readable( $config_path ) ) {
			return $config_path;
		} else {
			$this->_global_config_path_debug = 'No readable global config found';
			return false;
		}
	}

	/**
	 * Get the path to the project-specific configuration
	 * YAML file.
	 * ee-cli.local.yml takes priority over ee-cli.yml.
	 *
	 * @return string|false
	 */
	private function get_project_config_path() {
		$config_files = array(
			'ee-cli.local.yml',
			'ee-cli.yml'
		);

		// Stop looking upward when we find we have emerged from a subdirectory
		// install into a parent install
		$project_config_path = Utils\find_file_upward( $config_files, getcwd(), function ( $dir ) {
			static $wp_load_count = 0;
			$wp_load_path = $dir . DIRECTORY_SEPARATOR . 'wp-load.php';
			if ( file_exists( $wp_load_path ) ) {
				$wp_load_count += 1;
			}
			return $wp_load_count > 1;
		} );
		if ( ! empty( $project_config_path ) ) {
			$this->_project_config_path_debug = 'Using project config: ' . $project_config_path;
		} else {
			$this->_project_config_path_debug = 'No project config found';
		}
		return $project_config_path;
	}

	private function cmd_starts_with( $prefix ) {
		return $prefix == array_slice( $this->arguments, 0, count( $prefix ) );
	}

	/**
	 * Given positional arguments, find the command to execute.
	 *
	 * @param array $args
	 * @return array|string Command, args, and path on success; error message on failure
	 */
	public function find_command_to_run( $args ) {
		$command = \EE_CLI::get_root_command();

		$cmd_path = array();

		while ( !empty( $args ) && $command->can_have_subcommands() ) {
			$cmd_path[] = $args[0];
			$full_name = implode( ' ', $cmd_path );

			$subcommand = $command->find_subcommand( $args );

			if ( !$subcommand ) {
				if ( count( $cmd_path ) > 1 ) {
					$child = array_pop( $cmd_path );
					$parent_name = implode( ' ', $cmd_path );
					return sprintf(
						"'%s' is not a registered subcommand of '%s'. See 'ee help %s'.",
						$child,
						$parent_name,
						$parent_name
					);
				} else {
					return sprintf(
						"'%s' is not a registered ee command. See 'ee help'.",
						$full_name
					);
				}
			}

		/*	if ( $this->is_command_disabled( $subcommand ) ) {
				return sprintf(
					"The '%s' command has been disabled from the config file.",
					$full_name
				);
			}
		*/

			$command = $subcommand;
		}

		return array( $command, $args, $cmd_path );
	}

	/**
	 * Find the WP-CLI command to run given arguments,
	 * and invoke it.
	 *
	 * @param array $args Positional arguments including command name
	 * @param array $assoc_args
	 */
	public function run_command( $args, $assoc_args = array() ) {
		$r = $this->find_command_to_run( $args );
		if ( is_string( $r ) ) {
			EE_CLI::error( $r );
		}

		list( $command, $final_args, $cmd_path ) = $r;

		$name = implode( ' ', $cmd_path );

		if ( isset( $this->extra_config[ $name ] ) ) {
			$extra_args = $this->extra_config[ $name ];
		} else {
			$extra_args = array();
		}

		EE_CLI::debug( 'Running command: ' . $name );
		try {
			$command->invoke( $final_args, $assoc_args, $extra_args );
		} catch ( EE_CLI\Iterators\Exception $e ) {
			EE_CLI::error( $e->getMessage() );
		}
	}

	private function _run_command() {
		$this->run_command( $this->arguments, $this->assoc_args );
	}

	/**
	 * Check whether a given command is disabled by the config
	 *
	 * @return bool
	 */
	/*public function is_command_disabled( $command ) {
		$path = implode( ' ', array_slice( \EE_CLI\Dispatcher\get_path( $command ), 1 ) );
		return in_array( $path, $this->config['disabled_commands'] );
	}*/

	/**
	 * Transparently convert deprecated syntaxes
	 *
	 * @param array $args
	 * @param array $assoc_args
	 * @return array
	 */
	private static function back_compat_conversions( $args, $assoc_args ) {
		$top_level_aliases = array(
			'sql' => 'db',
			'blog' => 'site'
		);
		if ( count( $args ) > 0 ) {
			foreach ( $top_level_aliases as $old => $new ) {
				if ( $old == $args[0] ) {
					$args[0] = $new;
					break;
				}
			}
		}

		// --json  ->  --format=json
		if ( isset( $assoc_args['json'] ) ) {
			$assoc_args['format'] = 'json';
			unset( $assoc_args['json'] );
		}

		// --{version|info}  ->  cli {version|info}
		if ( empty( $args ) ) {
			$special_flags = array( 'version', 'info' );
			foreach ( $special_flags as $key ) {
				if ( isset( $assoc_args[ $key ] ) ) {
					$args = array( 'cli', $key );
					unset( $assoc_args[ $key ] );
					break;
				}
			}
		}

		return array( $args, $assoc_args );
	}

	/*
		Dynamic arguments for stack to be checked before parsing
	*/
	private function stack_work($args, $assoc_args)
	{
		$configurator = \EE_CLI::get_configurator();
		print_r($args);
		print_r($assoc_args);
		foreach ( $assoc_args as $key => $value) {
			if ($args[0] === 'stack') {
				if ($args[1] === 'install'){
					// check if key matches any stack config
					// otherwise throw error if not exists
					if($configurator->check_stack_exists($this->global_config_path, $key)){
						unset( $assoc_args[ $key ] );
					}
					else {
						print("Error:");
					}
				}
			}
		}

		return array( $args, $assoc_args );
	}

	/**
	 * Whether or not the output should be rendered in color
	 *
	 * @return bool
	 */
	public function in_color() {
		return $this->colorize;
	}

	private function init_colorization() {
		if ( 'auto' === $this->config['color'] ) {
			$this->colorize = ( !\cli\Shell::isPiped() && !\EE_CLI\Utils\is_windows() );
		} else {
			$this->colorize = $this->config['color'];
		}
	}

	private function init_logger() {
		if ( $this->config['quiet'] )
			$logger = new \EE_CLI\Loggers\Quiet;
		else
			$logger = new \EE_CLI\Loggers\Regular( $this->in_color() );

		EE_CLI::set_logger( $logger );
	}


	private function init_config() {
		$configurator = \EE_CLI::get_configurator();

		// File config
		{
			$this->global_config_path = $this->get_global_config_path();
			$this->project_config_path = $this->get_project_config_path();

			$configurator->merge_yml( $this->global_config_path );
			$configurator->merge_yml( $this->project_config_path );
		}

		// Runtime config and args
		{
			list( $args, $assoc_args, $runtime_config ) = $configurator->parse_args(
				array_slice( $GLOBALS['argv'], 1 ) );

			list( $this->arguments, $this->assoc_args ) = self::back_compat_conversions(
				$args, $assoc_args );

			list( $this->arguments, $this->assoc_args ) = self::stack_work(
				$args, $assoc_args );

			$configurator->merge_array( $runtime_config );
		}

		list( $this->config, $this->extra_config ) = $configurator->to_array();
	}


	public function start() {
		$this->init_config();
		$this->init_colorization();
		$this->init_logger();

		print_r($this->assoc_args);
		EE_CLI::debug( $this->_global_config_path_debug );
		EE_CLI::debug( $this->_project_config_path_debug );

	//	$this->check_root();

		if ( empty( $this->arguments ) )
			$this->arguments[] = 'help';

		// Protect 'cli info' from most of the runtime
		if ( 'cli' === $this->arguments[0] && ! empty( $this->arguments[1] ) && 'info' === $this->arguments[1] ) {
			$this->_run_command();
			exit;
		}

		// Load bundled commands early, so that they're forced to use the same
		// APIs as non-bundled commands.
		Utils\load_command( $this->arguments[0] );

		if ( isset( $this->config['require'] ) ) {
			foreach ( $this->config['require'] as $path ) {
				if ( ! file_exists( $path ) ) {
					EE_CLI::error( sprintf( "Required file '%s' doesn't exist", basename( $path ) ) );
				}
				Utils\load_file( $path );
				EE_CLI::debug( 'Required file from config: ' . $path );
			}
		}

		// Show synopsis if it's a composite command.
		$r = $this->find_command_to_run( $this->arguments );
		if ( is_array( $r ) ) {
			list( $command ) = $r;

			if ( $command->can_have_subcommands() ) {
				$command->show_usage();
				exit;
			}
		}

		// First try at showing man page
		if ( 'help' === $this->arguments[0] ) {
			$this->_run_command();
		}

		//$this->check_wp_version();



		$this->_run_command();

	}
}
