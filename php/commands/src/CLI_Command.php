<?php

use \Composer\Semver\Comparator;
use \EE\Utils;
use Mustangostang\Spyc;

/**
 * Review current EE info, check for updates, or see defined aliases.
 *
 * ## EXAMPLES
 *
 *     # Display the version currently installed.
 *     $ ee cli version
 *     EE 0.24.1
 *
 *     # Check for updates to EE.
 *     $ ee cli check-update
 *     Success: EE is at the latest version.
 *
 *     # Update EE to the latest stable release.
 *     $ ee cli update
 *     You have version 0.24.0. Would you like to update to 0.24.1? [y/n] y
 *     Downloading from https://github.com/ee/ee/releases/download/v0.24.1/ee-0.24.1.phar...
 *     New version works. Proceeding to replace.
 *     Success: Updated EE to 0.24.1.
 */
class CLI_Command extends EE_Command {

	private function command_to_array( $command ) {
		$dump = array(
			'name'        => $command->get_name(),
			'description' => $command->get_shortdesc(),
			'longdesc'    => $command->get_longdesc(),
		);

		foreach ( $command->get_subcommands() as $subcommand ) {
			$dump['subcommands'][] = $this->command_to_array( $subcommand );
		}

		if ( empty( $dump['subcommands'] ) ) {
			$dump['synopsis'] = (string) $command->get_synopsis();
		}

		return $dump;
	}

	/**
	 * Print EE version.
	 *
	 * ## EXAMPLES
	 *
	 *     # Display CLI version.
	 *     $ ee cli version
	 *     EE 0.24.1
	 */
	public function version() {
		EE::line( 'EE ' . EE_VERSION );
	}

	/**
	 * Print various details about the EE environment.
	 *
	 * Helpful for diagnostic purposes, this command shares:
	 *
	 * * OS information.
	 * * Shell information.
	 * * PHP binary used.
	 * * PHP binary version.
	 * * php.ini configuration file used (which is typically different than web).
	 * * EE root dir: where EE is installed (if non-Phar install).
	 * * EE global config: where the global config YAML file is located.
	 * * EE project config: where the project config YAML file is located.
	 * * EE version: currently installed version.
	 *
	 * See [config docs](https://ee.org/config/) for more details on global
	 * and project config YAML files.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: list
	 * options:
	 *   - list
	 *   - json
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Display various data about the CLI environment.
	 *     $ ee cli info
	 *     OS:  Linux 4.10.0-42-generic #46~16.04.1-Ubuntu SMP Mon Dec 4 15:57:59 UTC 2017 x86_64
	 *     Shell:   /usr/bin/zsh
	 *     PHP binary:  /usr/bin/php
	 *     PHP version: 7.1.12-1+ubuntu16.04.1+deb.sury.org+1
	 *     php.ini used:    /etc/php/7.1/cli/php.ini
	 *     EE root dir:    phar://ee.phar
	 *     EE packages dir:    /home/person/.ee/packages/
	 *     EE global config:
	 *     EE project config:
	 *     EE version: 1.5.0
	 */
	public function info( $_, $assoc_args ) {
		$php_bin = Utils\get_php_binary();

		$system_os = sprintf( '%s %s %s %s', php_uname( 's' ), php_uname( 'r' ), php_uname( 'v' ), php_uname( 'm' ) );
		$shell     = getenv( 'SHELL' );
		if ( ! $shell && Utils\is_windows() ) {
			$shell = getenv( 'ComSpec' );
		}
		$runner = EE::get_runner();

		$packages_dir = $runner->get_packages_dir_path();
		if ( ! is_dir( $packages_dir ) ) {
			$packages_dir = null;
		}
		if ( \EE\Utils\get_flag_value( $assoc_args, 'format' ) === 'json' ) {
			$info = array(
				'php_binary_path'      => $php_bin,
				'global_config_path'   => $runner->global_config_path,
				'project_config_path'  => $runner->project_config_path,
				'ee_dir_path'          => EE_ROOT,
				'ee_packages_dir_path' => $packages_dir,
				'ee_version'           => EE_VERSION,
				'system_os'            => $system_os,
				'shell'                => $shell,
			);

			EE::line( json_encode( $info ) );
		} else {

			$info = array(
				array( 'OS', $system_os ),
				array( 'Shell', $shell ),
				array( 'PHP binary', $php_bin ),
				array( 'PHP version', PHP_VERSION ),
				array( 'php.ini used', get_cfg_var( 'cfg_file_path' ) ),
				array( 'EE root dir', EE_ROOT ),
				array( 'EE vendor dir', EE_VENDOR_DIR ),
				array( 'EE phar path', ( defined( 'EE_PHAR_PATH' ) ? EE_PHAR_PATH : '' ) ),
				array( 'EE packages dir', $packages_dir ),
				array( 'EE global config', $runner->global_config_path ),
				array( 'EE project config', $runner->project_config_path ),
				array( 'EE version', EE_VERSION ),
			);

			$info_table = new \cli\Table();
			$info_table->setRows( $info );
			$info_table->setRenderer( new \cli\table\Ascii() );
			$lines = array_slice( $info_table->getDisplayLines(), 2 );
			foreach ( $lines as $line ) {
				\EE::line( $line );
			}
		}
	}

	/**
	 * Update EE to the latest release.
	 *
	 * Default behavior is to check the releases API for the newest stable
	 * version, and prompt if one is available.
	 *
	 * Use `--stable` to install or reinstall the latest stable version.
	 *
	 * Use `--nightly` to install the latest built version of the develop branch.
	 * While not recommended for production, nightly contains the latest and
	 * greatest, and should be stable enough for development and staging
	 * environments.
	 *
	 * ## OPTIONS
	 *
	 * [--stable]
	 * : Update to the latest stable release.
	 *
	 * [--nightly]
	 * : Update to the latest built version of the develop branch. Potentially unstable.
	 *
	 * ## EXAMPLES
	 *
	 *     # Update CLI.
	 *     $ ee cli update
	 *     You have version 0.24.0. Would you like to update to 0.24.1? [y/n] y
	 *     Downloading from https://github.com/ee/ee/releases/download/v0.24.1/ee-0.24.1.phar...
	 *     New version works. Proceeding to replace.
	 *     Success: Updated EE to 0.24.1.
	 */
	public function update( $_, $assoc_args ) {

		$config_file_path = getenv( 'EE_CONFIG_PATH' ) ? getenv( 'EE_CONFIG_PATH' ) : EE_CONF_ROOT . '/config.yml';

		$existing_config = Spyc::YAMLLoad( $config_file_path );

		if ( Utils\get_flag_value( $assoc_args, 'nightly' ) ) {
			$existing_config['ee_installer_version'] = 'nightly';
		} else {
			$existing_config['ee_installer_version'] = 'stable';
		}

		$config_file = fopen( $config_file_path, "w" );
		fwrite( $config_file, Spyc::YAMLDump( $existing_config ) );
		fclose( $config_file );

		file_put_contents( EE_CONF_ROOT . '/update.sh', file_get_contents( 'http://rt.cx/eev4' ) );
		if ( \EE\Utils\default_launch( 'bash ' . EE_CONF_ROOT . '/update.sh' ) ) {
			EE::success( 'Update complete.' );
			unlink( EE_CONF_ROOT . '/update.sh' );
		} else {
			EE::error( 'There was some error in running update. Please check logs and re-run update.' );
		}
	}

	/**
	 * Dump the list of global parameters, as JSON or in var_export format.
	 *
	 * ## OPTIONS
	 *
	 * [--with-values]
	 * : Display current values also.
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: json
	 * options:
	 *   - var_export
	 *   - json
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Dump the list of global parameters.
	 *     $ ee cli param-dump --format=var_export
	 *     array (
	 *       'path' =>
	 *       array (
	 *         'runtime' => '=<path>',
	 *         'file' => '<path>',
	 *         'synopsis' => '',
	 *         'default' => NULL,
	 *         'multiple' => false,
	 *         'desc' => 'Path to the WordPress files.',
	 *       ),
	 *       'url' =>
	 *       array (
	 *
	 * @subcommand param-dump
	 */
	public function param_dump( $_, $assoc_args ) {
		$spec = \EE::get_configurator()->get_spec();

		if ( \EE\Utils\get_flag_value( $assoc_args, 'with-values' ) ) {
			$config = \EE::get_configurator()->to_array();
			// Copy current config values to $spec
			foreach ( $spec as $key => $value ) {
				$current = null;
				if ( isset( $config[0][$key] ) ) {
					$current = $config[0][$key];
				}
				$spec[$key]['current'] = $current;
			}
		}

		if ( 'var_export' === \EE\Utils\get_flag_value( $assoc_args, 'format' ) ) {
			var_export( $spec );
		} else {
			echo json_encode( $spec );
		}
	}

	/**
	 * Dump the list of installed commands, as JSON.
	 *
	 * ## EXAMPLES
	 *
	 *     # Dump the list of installed commands.
	 *     $ ee cli cmd-dump
	 *     {"name":"ee","description":"Manage WordPress through the command-line.","longdesc":"\n\n## GLOBAL PARAMETERS\n\n  --path=<path>\n      Path to the WordPress files.\n\n  --ssh=<ssh>\n      Perform operation against a remote server over SSH (or a container using scheme of "docker" or "docker-compose").\n\n  --url=<url>\n      Pretend request came from given URL. In multisite, this argument is how the target site is specified. \n\n  --user=<id|login|email>\n
     *
	 * @subcommand cmd-dump
	 */
	public function cmd_dump() {
		echo json_encode( $this->command_to_array( EE::get_root_command() ) );
	}

	/**
	 * Generate tab completion strings.
	 *
	 * ## OPTIONS
	 *
	 * --line=<line>
	 * : The current command line to be executed.
	 *
	 * --point=<point>
	 * : The index to the current cursor position relative to the beginning of the command.
	 *
	 * ## EXAMPLES
	 *
	 *     # Generate tab completion strings.
	 *     $ ee cli completions --line='ee eva' --point=100
	 *     eval
	 *     eval-file
	 */
	public function completions( $_, $assoc_args ) {
		$line  = substr( $assoc_args['line'], 0, $assoc_args['point'] );
		$compl = new \EE\Completions( $line );
		$compl->render();
	}

	/**
	 * List available EE aliases.
	 *
	 * Aliases are shorthand references to WordPress installs. For instance,
	 * `@dev` could refer to a development install and `@prod` could refer to
	 * a production install. This command gives you visibility in what
	 * registered aliases you have available.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: yaml
	 * options:
	 *   - yaml
	 *   - json
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # List all available aliases.
	 *     $ ee cli alias
	 *     ---
	 * @all   : Run command against every registered alias.
	 * @prod  :
	 *       ssh: runcommand@runcommand.io~/webapps/production
	 * @dev   :
	 *       ssh: vagrant@192.168.50.10/srv/www/runcommand.dev
	 * @both  :
	 *       - @prod
	 *       - @dev
	 *
	 * @alias aliases
	 */
	public function alias( $_, $assoc_args ) {
		EE::print_value( EE::get_runner()->aliases, $assoc_args );
	}

	/**
	 * Get a string representing the type of update being checked for.
	 */
	private function get_update_type_str( $assoc_args ) {
		$update_type = ' ';
		foreach ( array( 'major', 'minor', 'patch' ) as $type ) {
			if ( true === \EE\Utils\get_flag_value( $assoc_args, $type ) ) {
				$update_type = ' ' . $type . ' ';
				break;
			}
		}

		return $update_type;
	}

	/**
	 * Detects if a command exists
	 *
	 * This commands checks if a command is registered with EE.
	 * If the command is found then it returns with exit status 0.
	 * If the command doesn't exist, then it will exit with status 1.
	 *
	 * ## OPTIONS
	 * <command_name>...
	 * : The command
	 *
	 * ## EXAMPLES
	 *
	 *     # The "site delete" command is registered.
	 *     $ ee cli has-command "site delete"
	 *     $ echo $?
	 *     0
	 *
	 *     # The "foo bar" command is not registered.
	 *     $ ee cli has-command "foo bar"
	 *     $ echo $?
	 *     1
	 *
	 * @subcommand has-command
	 */
	public function has_command( $_, $assoc_args ) {

		// If command is input as a string, then explode it into array.
		$command = explode( ' ', implode( ' ', $_ ) );

		EE::halt( is_array( EE::get_runner()->find_command_to_run( $command ) ) ? 0 : 1 );
	}
}
