<?php

use EE\Model\Site;
use function EE\Site\Utils\auto_site_name;
use function EE\Site\Utils\get_site_info;

/**
 * Perform operations on log files.
 *
 * ## EXAMPLES
 *
 *     # Display site logs.
 *     $ ee log show example.com
 *     watching logfile example.com/logs/nginx/error.log
 *     watching logfile example.com/logs/nginx/access.log
 *     watching logfile example.com/logs/php/error.log
 *     watching logfile example.com/logs/php/access.log
 *
 */
class LOG_Command extends EE_Command {


	/**
	 * @var object $site_data Object containing site related information.
	 */
	private $site_data;

	/**
	 * @var array $logs Array of all logs to be monitored.
	 */
	private $logs = [];

	/**
	 * Monitor site or global logs.
	 *
	 * ## OPTIONS
	 *
	 * [<site-name>]
	 * : Name of website.
	 *
	 * [--n=<line-count>]
	 * : Start from last number of given lines.
	 * ---
	 * default: 10
	 * ---
	 *
	 * [--global]
	 * : Displays all logs including all sites, all services and cli.
	 *
	 * [--cli]
	 * : Displays EasyEngine's own logs.
	 *
	 * [--all]
	 * : Displays all local logs, including service logs.
	 *
	 * [--nginx]
	 * : Displays nginx logs for a site.
	 *
	 * [--php]
	 * : Displays php logs for a site.
	 *
	 * [--wp]
	 * : Displays wp debug log for a site.
	 *
	 * [--access]
	 * : Displays nginx & php access logs for a site.
	 *
	 * [--error]
	 * : Displays nginx & php error logs for a site.
	 *
	 * ## EXAMPLES
	 *
	 *     # Show all logs.
	 *     $ ee log show example.com --all
	 *     watching logfile ~/easyengine/sites/example.com/logs/nginx/access.log
	 *     watching logfile ~/easyengine/sites/example.com/logs/nginx/error.log
	 *     watching logfile ~/easyengine/sites/example.com/logs/debug.log
	 *
	 *     # Show debug log for site.
	 *     $ ee log show example.com --wp
	 *     watching logfile ~/easyengine/sites/example.com/logs/debug.log
	 *
	 */
	public function show( $args, $assoc_args ) {

		$final_string = '';

		$is_global = false;
		$is_cli    = false;

		// Possible logs for a site.
		$allowed_types = [ 'nginx', 'php', 'wp', 'access', 'error' ];

		$lines = empty( $assoc_args['n'] ) ? 10 : $assoc_args['n'];

		$passed_types = array_keys( $assoc_args );

		if ( ( $key = array_search( 'n', $passed_types ) ) !== false ) {
			unset( $passed_types[ $key ] ); // Remove line count.
		}

		if ( empty( $passed_types ) ) {
			$final_types = [ 'nginx', 'php', 'wp' ];
		} elseif ( in_array( 'all', $passed_types ) ) {
			$final_types = $allowed_types;
		} else {
			$final_types = array_intersect( $allowed_types, $passed_types );
		}

		if ( isset( $assoc_args['global'] ) ) {
			$is_global = true;
		}

		if ( isset( $assoc_args['cli'] ) ) {
			$is_cli = true;
		}

		if ( $is_global ) {

			$sites = Site::all();

			foreach ( $sites as $site ) {

				$logs_path = $site->site_fs_path . DIRECTORY_SEPARATOR . 'logs';

				if ( 'wp' === $site->site_type ) {
					$wp_logs_path = $site->site_fs_path . DIRECTORY_SEPARATOR . $this->create_directory_path( [ 'app', 'htdocs', 'wp-content' ] );
				}

				foreach ( $allowed_types as $type ) {
					if ( 'access' === $type || 'error' === $type ) {
						$log_type_path = $logs_path . DIRECTORY_SEPARATOR;
					} elseif ( 'wp' === $type ) {
						$log_type_path = $wp_logs_path;
					} else {
						$log_type_path = $logs_path . DIRECTORY_SEPARATOR . $type;
					}

					$this->get_files( $log_type_path, $type );
				}
			}
		} elseif ( $is_cli ) {
			$this->get_files( EE_ROOT_DIR . DIRECTORY_SEPARATOR . $this->create_directory_path( [ 'logs' ] ), 'cli' );
		} else {

			$this->populate_info( $args, __FUNCTION__ );

			$site_type    = $this->site_data->site_type;
			$logs_path    = $this->site_data->site_fs_path . DIRECTORY_SEPARATOR . 'logs';
			$wp_logs_path = $this->site_data->site_fs_path . DIRECTORY_SEPARATOR . $this->create_directory_path( [ 'app', 'htdocs', 'wp-content' ] );

			if ( 'html' === $site_type ) {
				unset( $final_types['php'], $final_types['wp'] );
			} elseif ( 'php' === $site_type ) {
				unset( $final_types['wp'] );
			}

			foreach ( $final_types as $type ) {
				if ( 'access' === $type || 'error' === $type ) {
					$log_type_path = $logs_path . DIRECTORY_SEPARATOR;
				} elseif ( 'wp' === $type ) {
					$log_type_path = $wp_logs_path;
				} else {
					$log_type_path = $logs_path . DIRECTORY_SEPARATOR . $type;
				}

				$this->get_files( $log_type_path, $type );
			}
		}

		$logs = array_unique( $this->logs );

		foreach ( $logs as $log ) {

			if ( ! empty( $log ) ) {
				// Remove 'WEBROOT' path and display log path from site name.
				$log_path = str_replace( WEBROOT, '', $log );

				EE::log( "watching logfile {$log_path}" );

				$final_string .= $log . ' ';
			}
		}

		if ( ! empty( $final_string ) ) {
			system( "tail -n $lines -f $final_string" );
		} else {
			EE::error( 'No logs found!' );
		}

	}

	/**
	 * Function to populate basic info from args
	 *
	 * @param array  $args    args passed from function.
	 * @param string $command command name that is calling the function.
	 *
	 * @return void.
	 */
	private function populate_info( $args, $command ) {
		$args            = auto_site_name( $args, 'log', $command );
		$this->site_data = get_site_info( $args, true, true, false );
	}

	/**
	 * Get All files in given path recursively.
	 *
	 * @param string $path Directory Path.
	 *
	 * @return array
	 */
	private function getDirContents( $path ) {

		$recursive_iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $path ) );

		$files = array();

		foreach ( $recursive_iterator as $file ) {
			if ( ! $file->isDir() ) {
				$files[] = $file->getPathname();
			}
		}

		return $files;
	}

	/**
	 * Add all valid logs to an internal array to be processed later.
	 *
	 * @param string $log_path Path to log file.
	 * @param string $type     Type of log file to be monitored.
	 *
	 * @return void
	 */
	private function get_files( $log_path, $type = '' ) {

		if ( is_dir( $log_path ) ) {

			if ( 'wp' === $type ) {
				$files = [ $log_path . 'debug.log' ];
			} elseif ( 'cli' === $type ) {
				$files = [ $log_path . 'ee.log' ];
			} else {
				$files = $this->getDirContents( $log_path );
			}

			foreach ( $files as $file ) {

				if ( ! file_exists( $file ) ) {
					EE::warning( "$type log doesn't exist!!" );
					continue;
				}

				$file_info = pathinfo( $file );

				if ( 'log' === $file_info['extension'] ) {
					if ( 'access' === $type ) {
						$this->logs[] = ( 'access' === $file_info['filename'] ) ? $file : '';
					} elseif ( 'error' === $type ) {
						$this->logs[] = ( 'error' === $file_info['filename'] ) ? $file : '';
					} else {
						$this->logs[] = $file;
					}
				}
			}
		}

	}

	/**
	 * Helper function to generate path from give directories.
	 *
	 * @param array $directories Array of directories in order to create path.
	 *
	 * @return string
	 */
	private function create_directory_path( $directories ) {

		$final_string = '';

		foreach ( $directories as $directory ) {
			$final_string .= $directory . DIRECTORY_SEPARATOR;
		}

		return $final_string;
	}

}
