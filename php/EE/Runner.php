<?php

namespace EE;

use EE;
use EE\Utils;
use EE\Dispatcher;

/**
 * Performs the execution of a command.
 *
 * @package EE
 */
class Runner {

	private $global_config_path, $project_config_path;

	private $config, $extra_config;

	private $arguments, $assoc_args;

	private $_early_invoke = array();

	private $_global_config_path_debug;

	private $_project_config_path_debug;

	private $_required_files;

	public function __get( $key ) {
		if ( '_' === $key[0] )
			return null;

		return $this->$key;
	}

	/**
	 * Register a command for early invocation, generally before WordPress loads.
	 *
	 * @param string $when Named execution hook
	 * @param EE\Dispatcher\Subcommand $command
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
		} else if ( getenv( 'EE_CONFIG_PATH' ) ) {
			$config_path = getenv( 'EE_CONFIG_PATH' );
			$this->_global_config_path_debug = 'Using global config from EE_CONFIG_PATH env var: ' . $config_path;
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

	/**
	 * Attempts to find the path to the WP install inside index.php
	 *
	 * @param string $index_path
	 * @return string|false
	 */
	private static function extract_subdir_path( $index_path ) {
		$index_code = file_get_contents( $index_path );

		if ( !preg_match( '|^\s*require\s*\(?\s*(.+?)/wp-blog-header\.php([\'"])|m', $index_code, $matches ) ) {
			return false;
		}

		$wp_path_src = $matches[1] . $matches[2];
		$wp_path_src = Utils\replace_path_consts( $wp_path_src, $index_path );
		$wp_path = eval( "return $wp_path_src;" );

		if ( !Utils\is_path_absolute( $wp_path ) ) {
			$wp_path = dirname( $index_path ) . "/$wp_path";
		}

		return $wp_path;
	}

	/**
	 * Guess which URL context ee-cli has been invoked under.
	 *
	 * @param array $assoc_args
	 * @return string|false
	 */
	private static function guess_url( $assoc_args ) {
		if ( isset( $assoc_args['blog'] ) ) {
			$assoc_args['url'] = $assoc_args['blog'];
		}

		if ( isset( $assoc_args['url'] ) ) {
			$url = $assoc_args['url'];
			if ( true === $url ) {
				EE::warning( 'The --url parameter expects a value.' );
			}
		}

		if ( isset( $url ) ) {
			return $url;
		}

		return false;
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
		$command = \EE::get_root_command();

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

			if ( $this->is_command_disabled( $subcommand ) ) {
				return sprintf(
					"The '%s' command has been disabled from the config file.",
					$full_name
				);
			}

			$command = $subcommand;
		}

		return array( $command, $args, $cmd_path );
	}

	/**
	 * Find the ee-cli command to run given arguments,
	 * and invoke it.
	 *
	 * @param array $args        Positional arguments including command name
	 * @param array $assoc_args  Associative arguments for the command.
	 * @param array $options     Configuration options for the function.
	 */
	public function run_command( $args, $assoc_args = array(), $options = array() ) {
		if ( ! empty( $options['back_compat_conversions'] ) ) {
			list( $args, $assoc_args ) = self::back_compat_conversions( $args, $assoc_args );
		}
		$r = $this->find_command_to_run( $args );
		if ( is_string( $r ) ) {
			EE::error( $r );
		}

		list( $command, $final_args, $cmd_path ) = $r;

		$name = implode( ' ', $cmd_path );

		if ( isset( $this->extra_config[ $name ] ) ) {
			$extra_args = $this->extra_config[ $name ];
		} else {
			$extra_args = array();
		}

		EE::debug( 'Running command: ' . $name );
		try {
			$command->invoke( $final_args, $assoc_args, $extra_args );
		} catch ( EE\Iterators\Exception $e ) {
			EE::error( $e->getMessage() );
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
	public function is_command_disabled( $command ) {
		$path = implode( ' ', array_slice( \EE\Dispatcher\get_path( $command ), 1 ) );
		if ( ! empty( $this->config['disabled_commands'] ) && is_array( $this->config['disabled_commands'] ) ) {
			return in_array( $path, $this->config['disabled_commands'] );
		}
		return false;
	}

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

		// *-meta  ->  * meta
		if ( !empty( $args ) && preg_match( '/(post|comment|user|network)-meta/', $args[0], $matches ) ) {
			array_shift( $args );
			array_unshift( $args, 'meta' );
			array_unshift( $args, $matches[1] );
		}

		// core (multsite-)install --admin_name=  ->  --admin_user=
		if ( count( $args ) > 0 && 'core' == $args[0] && isset( $assoc_args['admin_name'] ) ) {
			$assoc_args['admin_user'] = $assoc_args['admin_name'];
			unset( $assoc_args['admin_name'] );
		}

		// site --site_id=  ->  site --network_id=
		if ( count( $args ) > 0 && 'site' == $args[0] && isset( $assoc_args['site_id'] ) ) {
			$assoc_args['network_id'] = $assoc_args['site_id'];
			unset( $assoc_args['site_id'] );
		}

		// {plugin|theme} update-all  ->  {plugin|theme} update --all
		if ( count( $args ) > 1 && in_array( $args[0], array( 'plugin', 'theme' ) )
			&& $args[1] == 'update-all'
		) {
			$args[1] = 'update';
			$assoc_args['all'] = true;
		}

		// plugin scaffold  ->  scaffold plugin
		if ( array( 'plugin', 'scaffold' ) == array_slice( $args, 0, 2 ) ) {
			list( $args[0], $args[1] ) = array( $args[1], $args[0] );
		}

		// foo --help  ->  help foo
		if ( isset( $assoc_args['help'] ) ) {
			array_unshift( $args, 'help' );
			unset( $assoc_args['help'] );
		}

		// {post|user} list --ids  ->  {post|user} list --format=ids
		if ( count( $args ) > 1 && in_array( $args[0], array( 'post', 'user' ) )
			&& $args[1] == 'list'
			&& isset( $assoc_args['ids'] )
		) {
			$assoc_args['format'] = 'ids';
			unset( $assoc_args['ids'] );
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

		// (post|site) url  --> (post|site) list --*__in --field=url
		if ( count( $args ) >= 2 && in_array( $args[0], array( 'post', 'site' ) ) && 'url' === $args[1] ) {
			switch ( $args[0] ) {
				case 'post':
					$post_ids = array_slice( $args, 2 );
					$args = array( 'post', 'list' );
					$assoc_args['post__in'] = implode( ',', $post_ids );
					$assoc_args['post_type'] = 'any';
					$assoc_args['orderby'] = 'post__in';
					$assoc_args['field'] = 'url';
					break;
				case 'site':
					$site_ids = array_slice( $args, 2 );
					$args = array( 'site', 'list' );
					$assoc_args['site__in'] = implode( ',', $site_ids );
					$assoc_args['field'] = 'url';
					break;
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
			$this->colorize = ( !\cli\Shell::isPiped() && !\EE\Utils\is_windows() );
		} else {
			$this->colorize = $this->config['color'];
		}
	}

	private function init_logger() {
		if ( ! empty( $this->config['quiet'] ) )
			$logger = new \EE\Loggers\Quiet;
		else
			$logger = new \EE\Loggers\Regular( $this->in_color() );

		EE::set_logger( $logger );
	}

	private function init_config() {
		$configurator = \EE::get_configurator();

		// File config
		{
			$this->global_config_path = $this->get_global_config_path();
			$this->project_config_path = $this->get_project_config_path();

			$configurator->merge_yml( $this->global_config_path );
			$config = $configurator->to_array();
			if ( ! empty( $config[0]['require'] ) ) {
				$this->_required_files['global'] = $config[0]['require'];
			}
			$configurator->merge_yml( $this->project_config_path );
			$config = $configurator->to_array();
			if ( ! empty( $config[0]['require'] ) ) {
				$this->_required_files['project'] = $config[0]['require'];
			}
		}

		// Runtime config and args
		{
			list( $args, $assoc_args, $runtime_config ) = $configurator->parse_args(
				array_slice( $GLOBALS['argv'], 1 ) );

			list( $this->arguments, $this->assoc_args ) = self::back_compat_conversions(
				$args, $assoc_args );

			$configurator->merge_array( $runtime_config );
		}

		list( $this->config, $this->extra_config ) = $configurator->to_array();
		if ( ! empty( $this->config['require'] ) ) {
			$this->_required_files['runtime'] = $this->config['require'];
		}
	}

	private function check_root() {
		if ( $this->config['allow-root'] )
			return; # they're aware of the risks!
		if ( !function_exists( 'posix_geteuid') )
			return; # posix functions not available
		if ( posix_geteuid() !== 0 )
			return; # not root

		EE::error(
			"YIKES! It looks like you're running this as root. You probably meant to " .
			"run this as the user that your EasyEngine install exists under.\n" .
			"\n" .
			"If you REALLY mean to run this as root, we won't stop you, but just " .
			"bear in mind that any code on this site will then have full control of " .
			"your server, making it quite DANGEROUS.\n" .
			"\n" .
			"If you'd like to continue as root, please run this again, adding this " .
			"flag:  --allow-root\n" .
			"\n" .
			"If you'd like to run it as the user that this site is under, you can " .
			"run the following to become the respective user:\n" .
			"\n" .
			"    sudo -u USER -i -- wp <command>\n" .
			"\n"
		);
	}

	public function start() {
		$this->init_config();
		$this->init_colorization();
		$this->init_logger();

		EE::debug( $this->_global_config_path_debug );
		EE::debug( $this->_project_config_path_debug );
		
		//Commented this code as ee command run by using root user.
		//$this->check_root();

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
					$context = '';
					foreach( array( 'global', 'project', 'runtime' ) as $scope ) {
						if ( in_array( $path, $this->_required_files[ $scope ] ) ) {
							switch ( $scope ) {
								case 'global':
									$context = ' (from global ' . basename( $this->global_config_path ) . ')';
									break;
								case 'project':
									$context = ' (from project\'s ' . basename( $this->project_config_path ) . ')';
									break;
								case 'runtime':
									$context = ' (from runtime argument)';
									break;
							}
							break;
						}
					}
					EE::error( sprintf( "Required file '%s' doesn't exist%s.", basename( $path ), $context ) );
				}
				Utils\load_file( $path );
				EE::debug( 'Required file from config: ' . $path );
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

		// Handle --url parameter
		$url = self::guess_url( $this->config );
		if ( $url )
			\EE::set_url( $url );

		$this->do_early_invoke( 'before_ee_load' );

		$this->_run_command();
	}
}