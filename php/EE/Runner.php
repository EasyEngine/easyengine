<?php

namespace EE;

use EE;
use EE\Utils;
use EE\Dispatcher;
use EE\Dispatcher\CompositeCommand;
use Mustangostang\Spyc;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

/**
 * Performs the execution of a command.
 *
 * @package EE
 */
class Runner {

	private $global_config_path, $project_config_path;

	private $config, $extra_config;

	private $alias;

	private $aliases;

	private $arguments, $assoc_args, $runtime_config;

	private $colorize = false;

	private $_early_invoke = array();

	private $_global_config_path_debug;

	private $_project_config_path_debug;

	private $_required_files;

	public function __get( $key ) {
		if ( '_' === $key[0] ) {
			return null;
		}

		return $this->$key;
	}

	/**
	 * Function to check and create the root directory for ee.
	 */
	private function init_ee() {

		if ( ! is_dir( EE_CONF_ROOT ) ) {
			$user = getenv('USER');
			shell_exec('sudo mkdir -p ' . EE_CONF_ROOT);
		}

		$this->ensure_present_in_config( 'sites_path', Utils\get_home_dir(). '/ee-sites' );
		$this->ensure_present_in_config( 'locale', 'en_US' );
		$this->ensure_present_in_config( 'ee_installer_version', 'stable' );

		if ( ! is_dir( $this->config['sites_path'] ) ) {
			mkdir( $this->config['sites_path'] );
		}
		define( 'WEBROOT', \EE\Utils\trailingslashit( $this->config['sites_path'] ) );
		define( 'DB', EE_CONF_ROOT.'/ee.sqlite' );
		define( 'LOCALHOST_IP', '127.0.0.1' );
		define( 'TABLE', 'sites' );
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
		if ( ! isset( $this->_early_invoke[ $when ] ) ) {
			return;
		}

		// Search the value of @when from the command method.
		$real_when = '';
		$r = $this->find_command_to_run( $this->arguments );
		if ( is_array( $r ) ) {
			list( $command, $final_args, $cmd_path ) = $r;

			foreach ( $this->_early_invoke as $_when => $_path ) {
				foreach ( $_path as $cmd ) {
					if ( $cmd === $cmd_path ) {
						$real_when = $_when;
					}
				}
			}
		}

		foreach ( $this->_early_invoke[ $when ] as $path ) {
			if ( $this->cmd_starts_with( $path ) ) {
				if ( empty( $real_when ) || ( $real_when && $real_when === $when ) ) {
					$this->_run_command_and_exit();
				}
			}
		}
	}

	/**
	 * Get the path to the global configuration YAML file.
	 *
	 * @return string|false
	 */
	public function get_global_config_path() {

		if ( getenv( 'EE_CONFIG_PATH' ) ) {
			$config_path = getenv( 'EE_CONFIG_PATH' );
			$this->_global_config_path_debug = 'Using global config from EE_CONFIG_PATH env var: ' . $config_path;
		} else {
			$config_path = EE_CONF_ROOT . '/config.yml';
			$this->_global_config_path_debug = 'Using default global config: ' . $config_path;
		}

		if ( is_readable( $config_path ) ) {
			return $config_path;
		}

		$this->_global_config_path_debug = 'No readable global config found';

		return false;
	}

	/**
	 * Get the path to the project-specific configuration
	 * YAML file.
	 * ee.local.yml takes priority over ee.yml.
	 *
	 * @return string|false
	 */
	public function get_project_config_path() {
		$config_files = array(
			'ee.local.yml',
			'ee.yml',
		);

		// Stop looking upward when we find we have emerged from a subdirectory
		// install into a parent install
		$project_config_path = Utils\find_file_upward(
			$config_files,
			getcwd(),
			function ( $dir ) {
				return false;
			}
		);

		$this->_project_config_path_debug = 'No project config found';

		if ( ! empty( $project_config_path ) ) {
			$this->_project_config_path_debug = 'Using project config: ' . $project_config_path;
		}

		return $project_config_path;
	}

	/**
	 * Get the path to the packages directory
	 *
	 * @return string
	 */
	public function get_packages_dir_path() {
		if ( getenv( 'EE_PACKAGES_DIR' ) ) {
			$packages_dir = Utils\trailingslashit( getenv( 'EE_PACKAGES_DIR' ) );
		} else {
			$packages_dir = EE_CONF_ROOT . '/packages';
		}
		return $packages_dir;
	}

	private function cmd_starts_with( $prefix ) {
		return array_slice( $this->arguments, 0, count( $prefix ) ) === $prefix;
	}

	/**
	 * Given positional arguments, find the command to execute.
	 *
	 * @param array $args
	 * @return array|string Command, args, and path on success; error message on failure
	 */
	public function find_command_to_run( $args ) {
		$command = \EE::get_root_command();

		EE::do_hook( 'find_command_to_run_pre' );

		$cmd_path = array();

		while ( ! empty( $args ) && $command->can_have_subcommands() ) {
			$cmd_path[] = $args[0];
			$full_name = implode( ' ', $cmd_path );

			$subcommand = $command->find_subcommand( $args );

			if ( ! $subcommand ) {
				if ( count( $cmd_path ) > 1 ) {
					$child = array_pop( $cmd_path );
					$parent_name = implode( ' ', $cmd_path );
					$suggestion = $this->get_subcommand_suggestion( $child, $command );
					return sprintf(
						"'%s' is not a registered subcommand of '%s'. See 'ee help %s' for available subcommands.%s",
						$child,
						$parent_name,
						$parent_name,
						! empty( $suggestion ) ? PHP_EOL . "Did you mean '{$suggestion}'?" : ''
					);
				}

				$suggestion = $this->get_subcommand_suggestion( $full_name, $command );

				return sprintf(
					"'%s' is not a registered ee command. See 'ee help' for available commands.%s",
					$full_name,
					! empty( $suggestion ) ? PHP_EOL . "Did you mean '{$suggestion}'?" : ''
				);
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
	 * Find the EE command to run given arguments, and invoke it.
	 *
	 * @param array $args        Positional arguments including command name
	 * @param array $assoc_args  Associative arguments for the command.
	 * @param array $options     Configuration options for the function.
	 */
	public function run_command( $args, $assoc_args = array(), $options = array() ) {

		$r = $this->find_command_to_run( $args );
		if ( is_string( $r ) ) {
			EE::error( $r );
		}

		list( $command, $final_args, $cmd_path ) = $r;

		$name = implode( ' ', $cmd_path );

		$extra_args = array();

		if ( isset( $this->extra_config[ $name ] ) ) {
			$extra_args = $this->extra_config[ $name ];
		}

		EE::debug( 'Running command: ' . $name, 'bootstrap' );
		try {
			$command->invoke( $final_args, $assoc_args, $extra_args );
		} catch ( EE\Iterators\Exception $e ) {
			EE::error( $e->getMessage() );
		}
	}

	/**
	 * Show synopsis if the called command is a composite command
	 */
	public function show_synopsis_if_composite_command() {
		$r = $this->find_command_to_run( $this->arguments );
		if ( is_array( $r ) ) {
			list( $command ) = $r;

			if ( $command->can_have_subcommands() ) {
				$command->show_usage();
				exit;
			}
		}
	}

	private function _run_command_and_exit( $help_exit_warning = '' ) {
		$this->show_synopsis_if_composite_command();
		$this->run_command( $this->arguments, $this->assoc_args );
		if ( $this->cmd_starts_with( array( 'help' ) ) ) {
			// Help couldn't find the command so exit with suggestion.
			$suggestion_or_disabled = $this->find_command_to_run( array_slice( $this->arguments, 1 ) );
			if ( is_string( $suggestion_or_disabled ) ) {
				if ( $help_exit_warning ) {
					EE::warning( $help_exit_warning );
				}
				EE::error( $suggestion_or_disabled );
			}
			// Should never get here.
		}
		exit;
	}

	/**
	 * Perform a command against a remote server over SSH (or a container using
	 * scheme of "docker" or "docker-compose").
	 *
	 * @param string $connection_string Passed connection string.
	 * @return void
	 */
	private function run_ssh_command( $connection_string ) {

		EE::do_hook( 'before_ssh' );

		$bits = Utils\parse_ssh_url( $connection_string );

		$pre_cmd = getenv( 'EE_SSH_PRE_CMD' );
		if ( $pre_cmd ) {
			$pre_cmd = rtrim( $pre_cmd, ';' ) . '; ';
		}
		if ( ! empty( $bits['path'] ) ) {
			$pre_cmd .= 'cd ' . escapeshellarg( $bits['path'] ) . '; ';
		}

		$env_vars = '';
		if ( getenv( 'EE_STRICT_ARGS_MODE' ) ) {
			$env_vars .= 'EE_STRICT_ARGS_MODE=1 ';
		}

		$ee_binary = 'ee';
		$ee_args = array_slice( $GLOBALS['argv'], 1 );

		if ( $this->alias && ! empty( $ee_args[0] ) && $this->alias === $ee_args[0] ) {
			array_shift( $ee_args );
			$runtime_alias = array();
			foreach ( $this->aliases[ $this->alias ] as $key => $value ) {
				if ( 'ssh' === $key ) {
					continue;
				}
				$runtime_alias[ $key ] = $value;
			}
			if ( ! empty( $runtime_alias ) ) {
				$encoded_alias = json_encode(
					array(
						$this->alias => $runtime_alias,
					)
				);
				$ee_binary = "EE_RUNTIME_ALIAS='{$encoded_alias}' {$ee_binary} {$this->alias}";
			}
		}

		foreach ( $ee_args as $k => $v ) {
			if ( preg_match( '#--ssh=#', $v ) ) {
				unset( $ee_args[ $k ] );
			}
		}

		$ee_command = $pre_cmd . $env_vars . $ee_binary . ' ' . implode( ' ', array_map( 'escapeshellarg', $ee_args ) );
		$escaped_command = $this->generate_ssh_command( $bits, $ee_command );

		passthru( $escaped_command, $exit_code );
		if ( 255 === $exit_code ) {
			EE::error( 'Cannot connect over SSH using provided configuration.', 255 );
		} else {
			exit( $exit_code );
		}
	}

	/**
	 * Generate a shell command from the parsed connection string.
	 *
	 * @param array  $bits       Parsed connection string.
	 * @param string $ee_command EE command to run.
	 * @return string
	 */
	private function generate_ssh_command( $bits, $ee_command ) {
		$escaped_command = '';

		// Set default values.
		foreach ( array( 'scheme', 'user', 'host', 'port', 'path' ) as $bit ) {
			if ( ! isset( $bits[ $bit ] ) ) {
				$bits[ $bit ] = null;
			}

			EE::debug( 'SSH ' . $bit . ': ' . $bits[ $bit ], 'bootstrap' );
		}

		$is_tty = function_exists( 'posix_isatty' ) && posix_isatty( STDOUT );

		if ( 'docker' === $bits['scheme'] ) {
			$command = 'docker exec %s%s%s sh -c %s';

			$escaped_command = sprintf(
				$command,
				$bits['user'] ? '--user ' . escapeshellarg( $bits['user'] ) . ' ' : '',
				$is_tty ? '-t ' : '',
				escapeshellarg( $bits['host'] ),
				escapeshellarg( $ee_command )
			);
		}

		if ( 'docker-compose' === $bits['scheme'] ) {
			$command = 'docker-compose exec %s%s%s sh -c %s';

			$escaped_command = sprintf(
				$command,
				$bits['user'] ? '--user ' . escapeshellarg( $bits['user'] ) . ' ' : '',
				$is_tty ? '' : '-T ',
				escapeshellarg( $bits['host'] ),
				escapeshellarg( $ee_command )
			);
		}

		// Vagrant ssh-config.
		if ( 'vagrant' === $bits['scheme'] ) {
			$command = 'vagrant ssh -c %s %s';

			$escaped_command = sprintf(
				$command,
				escapeshellarg( $ee_command ),
				escapeshellarg( $bits['host'] )
			);
		}

		// Default scheme is SSH.
		if ( 'ssh' === $bits['scheme'] || null === $bits['scheme'] ) {
			$command = 'ssh -q %s%s %s %s';

			if ( $bits['user'] ) {
				$bits['host'] = $bits['user'] . '@' . $bits['host'];
			}

			$escaped_command = sprintf(
				$command,
				$bits['port'] ? '-p ' . (int) $bits['port'] . ' ' : '',
				escapeshellarg( $bits['host'] ),
				$is_tty ? '-t' : '-T',
				escapeshellarg( $ee_command )
			);
		}

		EE::debug( 'Running SSH command: ' . $escaped_command, 'bootstrap' );

		return $escaped_command;
	}

	/**
	 * Check whether a given command is disabled by the config
	 *
	 * @return bool
	 */
	public function is_command_disabled( $command ) {
		$path = implode( ' ', array_slice( \EE\Dispatcher\get_path( $command ), 1 ) );
		return in_array( $path, $this->config['disabled_commands'] );
	}

	/**
	 * Whether or not the output should be rendered in color
	 *
	 * @return bool
	 */
	public function in_color() {
		return $this->colorize;
	}

	public function init_colorization() {
		if ( 'auto' === $this->config['color'] ) {
			$this->colorize = ( ! \EE\Utils\isPiped() && ! \EE\Utils\is_windows() );
		} else {
			$this->colorize = $this->config['color'];
		}
	}

	public function init_logger() {
		if ( $this->config['quiet'] ) {
			$logger = new \EE\Loggers\Quiet;
		} else {
			$logger = new \EE\Loggers\Regular( $this->in_color() );
		}

		EE::set_logger( $logger );

		if ( ! is_writable( EE_CONF_ROOT ) ) {
			EE::err( 'Please run `ee` as root user.' );
		}

		if ( !empty( $this->arguments[0] ) && 'cli' === $this->arguments[0] && ! empty( $this->arguments[1] ) && 'info' === $this->arguments[1] && ! $this->config['ssh'] ) {
			$file_logging_path = '/dev/null';
		}
		else {
			$file_logging_path = EE_CONF_ROOT . '/ee.log';
		}

		$dateFormat = 'd-m-Y H:i:s';
		$output     = "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n";
		$formatter  = new \Monolog\Formatter\LineFormatter( $output, $dateFormat, false, true );
		$stream     = new \Monolog\Handler\StreamHandler( EE_CONF_ROOT . '/ee.log', Logger::DEBUG );
		$stream->setFormatter( $formatter );
		$file_logger = new \Monolog\Logger( 'ee' );
		$file_logger->pushHandler( $stream );
		$file_logger->info( '::::::::::::::::::::::::ee invoked::::::::::::::::::::::::' );
		EE::set_file_logger( $file_logger );
	}

	public function get_required_files() {
		return $this->_required_files;
	}

	public function init_config() {
		$configurator = \EE::get_configurator();

		$argv = array_slice( $GLOBALS['argv'], 1 );

		$this->alias = null;
		if ( ! empty( $argv[0] ) && preg_match( '#' . Configurator::ALIAS_REGEX . '#', $argv[0], $matches ) ) {
			$this->alias = array_shift( $argv );
		}

		// File config
		{
			$this->global_config_path = $this->get_global_config_path();
			$this->project_config_path = $this->get_project_config_path();

			$configurator->merge_yml( $this->global_config_path, $this->alias );
			$config = $configurator->to_array();
			$this->_required_files['global'] = $config[0]['require'];
			$configurator->merge_yml( $this->project_config_path, $this->alias );
			$config = $configurator->to_array();
			$this->_required_files['project'] = $config[0]['require'];
		}

		// Runtime config and args
		{
			list( $args, $assoc_args, $this->runtime_config ) = $configurator->parse_args( $argv );

			// foo --help  ->  help foo
			if ( isset( $assoc_args['help'] ) && ! in_array( 'wp', $args ) ) {
				array_unshift( $args, 'help' );
				unset( $assoc_args['help'] );
			}

			if ( empty( $args ) && isset( $assoc_args['version'] ) ) {
				array_unshift( $args, 'version' );
				array_unshift( $args, 'cli' );
                unset( $assoc_args['version'] );
            }
			
			// ee3 backward compatibility to wp-cli flags
			$wp_compat_array_map = array(
				'user' => 'admin_user',
				'pass' => 'admin_pass',
				'email' => 'admin_email'
			);

			foreach ( $wp_compat_array_map as $from => $to ) {
				if ( isset( $assoc_args[$from] ) ) {
					$assoc_args[$to] = $assoc_args[$from];
					unset( $assoc_args[$from] );
				}
			}

			// backward compatibility message
			$unsupported_create_old_args = array(
				'w3tc',
				'wpsc',
				'wpfc',
				'pagespeed',
			);

			$old_arg = array_intersect( $unsupported_create_old_args, array_keys( $assoc_args ) );

			$old_args = implode(' --',$old_arg);
			if ( isset($args[1]) && 'create' === $args[1] && ! empty ($old_arg) ) {
				\EE::error( "Sorry, --$old_args flag/s is/are no longer supported in EE v4.\nPlease run `ee help " . implode( ' ', $args ) . '`.' );
			}

			list( $this->arguments, $this->assoc_args ) = [ $args, $assoc_args ];

			$configurator->merge_array( $this->runtime_config );
		}

		list( $this->config, $this->extra_config ) = $configurator->to_array();
		$this->aliases = $configurator->get_aliases();
		if ( count( $this->aliases ) && ! isset( $this->aliases['@all'] ) ) {
			$this->aliases = array_reverse( $this->aliases );
			$this->aliases['@all'] = 'Run command against every registered alias.';
			$this->aliases = array_reverse( $this->aliases );
		}
		$this->_required_files['runtime'] = $this->config['require'];
	}

	/**
	 * Ensures that vars are present in config. If they aren't, attempts to
	 * create config file and add vars in it.
	 *
	 * @param $var Variable to check.
	 * @param $default Default value to use if $var is not set.
	 */
	private function ensure_present_in_config( $var, $default) {

		if ( empty($this->config[$var]) ) {
			$this->config[$var] =  $default ;

			$config_file_path = getenv('EE_CONFIG_PATH') ? getenv('EE_CONFIG_PATH') : EE_CONF_ROOT . '/config.yml';
			$config_dir_path = dirname( $config_file_path );

			if ( file_exists( $config_file_path ) ) {
				if ( is_readable( $config_file_path ) ) {
					if ( is_writable( $config_file_path ) ) {
						$existing_config = Spyc::YAMLLoad ( $config_file_path );
						$this->add_var_to_config_file( $var, $config_file_path, $existing_config );
						return;
					}
					EE::error("The config file {$config_file_path} is not writable. Please set a config path which is writable in EE_CONFIG_PATH environment variable.");
				}
				EE::error("The config file {$config_file_path} is not readable. Please select a config path which is readable in EE_CONFIG_PATH environment variable.");
			}
			else {
				if ( is_writable( $config_dir_path ) ) {
					$this->add_var_to_config_file( $var, $config_file_path );
					return;
				}
				$mkdir_success = mkdir ( $config_dir_path , 0755, true );
				if ( ! $mkdir_success ) {
					EE::err("The config file path ${$config_dir_path} is not writable.\n Please run `ee` it as root user.");
				}
				$this->add_var_to_config_file($var, $config_file_path );
			}
		}
	}

	private function add_var_to_config_file( $var, $config_file_path, $config = [] ) {
		$config[$var] = $this->config[$var];

		$config_file = fopen( $config_file_path, "w" );
		fwrite( $config_file, Spyc::YAMLDump( $config ) );
		fclose( $config_file );
	}

	private function run_alias_group( $aliases ) {
		Utils\check_proc_available( 'group alias' );

		$php_bin = escapeshellarg( Utils\get_php_binary() );

		$script_path = $GLOBALS['argv'][0];

		if ( getenv( 'EE_CONFIG_PATH' ) ) {
			$config_path = getenv( 'EE_CONFIG_PATH' );
		} else {
			$config_path = EE_CONF_ROOT . '/config.yml';
		}
		$config_path = escapeshellarg( $config_path );

		foreach ( $aliases as $alias ) {
			EE::log( $alias );
			$args = implode( ' ', array_map( 'escapeshellarg', $this->arguments ) );
			$assoc_args = Utils\assoc_args_to_str( $this->assoc_args );
			$runtime_config = Utils\assoc_args_to_str( $this->runtime_config );
			$full_command = "EE_CONFIG_PATH={$config_path} {$php_bin} {$script_path} {$alias} {$args}{$assoc_args}{$runtime_config}";
			$proc = Utils\proc_open_compat( $full_command, array( STDIN, STDOUT, STDERR ), $pipes );
			proc_close( $proc );
		}
	}

	private function set_alias( $alias ) {
		$orig_config = $this->config;
		$alias_config = $this->aliases[ $this->alias ];
		$this->config = array_merge( $orig_config, $alias_config );
		foreach ( $alias_config as $key => $_ ) {
			if ( isset( $orig_config[ $key ] ) && ! is_null( $orig_config[ $key ] ) ) {
				$this->assoc_args[ $key ] = $orig_config[ $key ];
			}
		}
	}

	public function start() {

		$this->init_ee();

		// Enable PHP error reporting to stderr if testing.
		if ( getenv( 'BEHAT_RUN' ) ) {
			$this->enable_error_reporting();
		}

		EE::debug( $this->_global_config_path_debug, 'bootstrap' );
		EE::debug( $this->_project_config_path_debug, 'bootstrap' );
		EE::debug( 'argv: ' . implode( ' ', $GLOBALS['argv'] ), 'bootstrap' );

		if ( $this->alias ) {
			if ( '@all' === $this->alias && ! isset( $this->aliases['@all'] ) ) {
				EE::error( "Cannot use '@all' when no aliases are registered." );
			}

			if ( '@all' === $this->alias && is_string( $this->aliases['@all'] ) ) {
				$aliases = array_keys( $this->aliases );
				$k = array_search( '@all', $aliases );
				unset( $aliases[ $k ] );
				$this->run_alias_group( $aliases );
				exit;
			}

			if ( ! array_key_exists( $this->alias, $this->aliases ) ) {
				$error_msg = "Alias '{$this->alias}' not found.";
				$suggestion = Utils\get_suggestion( $this->alias, array_keys( $this->aliases ), $threshold = 2 );
				if ( $suggestion ) {
					$error_msg .= PHP_EOL . "Did you mean '{$suggestion}'?";
				}
				EE::error( $error_msg );
			}
			// Numerically indexed means a group of aliases
			if ( isset( $this->aliases[ $this->alias ][0] ) ) {
				$group_aliases = $this->aliases[ $this->alias ];
				$all_aliases = array_keys( $this->aliases );
				if ( $diff = array_diff( $group_aliases, $all_aliases ) ) {
					EE::error( "Group '{$this->alias}' contains one or more invalid aliases: " . implode( ', ', $diff ) );
				}
				$this->run_alias_group( $group_aliases );
				exit;
			}

			$this->set_alias( $this->alias );
		}

		if ( empty( $this->arguments ) ) {
			$this->arguments[] = 'help';
		}

		if ( $this->config['ssh'] ) {
			$this->run_ssh_command( $this->config['ssh'] );
			return;
		}

		// First try at showing man page.
		if ( $this->cmd_starts_with( array( 'help' ) ) ) {
			$this->auto_check_update();
			$this->run_command( $this->arguments, $this->assoc_args );
			// Help didn't exit so failed to find the command at this stage.
		}

		$this->_run_command_and_exit();

	}

	/**
	 * Check whether there's a EE update available, and suggest update if so.
	 */
	private function auto_check_update() {

		// `ee cli update` only works with Phars at this time.
		if ( ! Utils\inside_phar() ) {
			return;
		}

		$existing_phar = realpath( $_SERVER['argv'][0] );
		// Phar needs to be writable to be easily updateable.
		if ( ! is_writable( $existing_phar ) || ! is_writable( dirname( $existing_phar ) ) ) {
			return;
		}

		// Only check for update when a human is operating.
		if ( ! function_exists( 'posix_isatty' ) || ! posix_isatty( STDOUT ) ) {
			return;
		}

		// Allow hosts and other providers to disable automatic check update.
		if ( getenv( 'EE_DISABLE_AUTO_CHECK_UPDATE' ) ) {
			return;
		}

		// Permit configuration of number of days between checks.
		$days_between_checks = getenv( 'EE_AUTO_CHECK_UPDATE_DAYS' );
		if ( false === $days_between_checks ) {
			$days_between_checks = 1;
		}

		$cache = EE::get_cache();
		$cache_key = 'ee-update-check';
		// Bail early on the first check, so we don't always check on an unwritable cache.
		if ( ! $cache->has( $cache_key ) ) {
			$cache->write( $cache_key, time() );
			return;
		}

		// Bail if last check is still within our update check time period.
		$last_check = (int) $cache->read( $cache_key );
		if ( time() - ( 24 * 60 * 60 * $days_between_checks ) < $last_check ) {
			return;
		}

		// In case the operation fails, ensure the timestamp has been updated.
		$cache->write( $cache_key, time() );

		// Check whether any updates are available.
		ob_start();
		EE::run_command(
			array( 'cli', 'check-update' ),
			array(
				'format' => 'count',
			)
		);
		$count = ob_get_clean();
		if ( ! $count ) {
			return;
		}

		// Looks like an update is available, so let's prompt to update.
		EE::run_command( array( 'cli', 'update' ) );
		// If the Phar was replaced, we can't proceed with the original process.
		exit;
	}

	/**
	 * Get a suggestion on similar (sub)commands when the user entered an
	 * unknown (sub)command.
	 *
	 * @param string           $entry        User entry that didn't match an
	 *                                       existing command.
	 * @param CompositeCommand $root_command Root command to start search for
	 *                                       suggestions at.
	 *
	 * @return string Suggestion that fits the user entry, or an empty string.
	 */
	private function get_subcommand_suggestion( $entry, CompositeCommand $root_command = null ) {
		$commands = array();
		$this->enumerate_commands( $root_command ?: \EE::get_root_command(), $commands );

		return Utils\get_suggestion( $entry, $commands, $threshold = 2 );
	}

	/**
	 * Recursive method to enumerate all known commands.
	 *
	 * @param CompositeCommand $command Composite command to recurse over.
	 * @param array            $list    Reference to list accumulating results.
	 * @param string           $parent  Parent command to use as prefix.
	 */
	private function enumerate_commands( CompositeCommand $command, array &$list, $parent = '' ) {
		foreach ( $command->get_subcommands() as $subcommand ) {
			/** @var CompositeCommand $subcommand */
			$command_string = empty( $parent )
				? $subcommand->get_name()
				: "{$parent} {$subcommand->get_name()}";

			$list[] = $command_string;

			$this->enumerate_commands( $subcommand, $list, $command_string );
		}
	}

	/**
	 * Enables (almost) full PHP error reporting to stderr.
	 */
	private function enable_error_reporting() {
		if ( E_ALL !== error_reporting() ) {
			// Don't enable E_DEPRECATED as old versions of WP use PHP 4 style constructors and the mysql extension.
			error_reporting( E_ALL & ~E_DEPRECATED );
		}
		ini_set( 'display_errors', 'stderr' );
	}
}
