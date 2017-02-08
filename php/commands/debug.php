<?php

use \EE\Utils;
use \EE\Dispatcher;

class debug_Command extends EE_Command {


	public function debug_nginx( $_argc ) {
//	"""Start/Stop Nginx debug"""

		$debug         = $_argc;
		$debug_address = '0.0.0.0/0';



        if(isset($debug['sitename'])) {
            $site_name = $debug['sitename'];
            $ee_domain = EE_Utils::validate_domain($site_name);
            $ee_site_info = get_site_info($site_name);
            $ee_site_webroot = $ee_site_info['site_path'];
        }
        if ( 'on' === $debug['nginx'] ) {
		    $log_path = "";
		    if( empty( $ee_domain ) ){
                if ( ! grep_string( '/etc/nginx/nginx.conf', 'debug_connection' ) ) {
                    EE::success( "Setting up Nginx debug connection for 0.0.0.0/0" );
                    EE::exec_cmd( "sed -i 's@events {@events {\\n\\tdebug_connection ". $debug_address .";@' /etc/nginx/nginx.conf");
                    $log_path = "/var/log/nginx/*.error.log";
                } else {
                    EE::success( "Nginx debug connection already enabled" );
                    $log_path = "/var/log/nginx/*.error.log";
                }
            } else {
                if(is_site_exist($ee_domain)){
                    $configFile = "/etc/nginx/sites-available/".$ee_domain;
                    if(ee_file_exists($configFile)){
                        if(!grep_string($configFile,"error.log debug")){
                            EE::exec_cmd("sed -i \"s/error.log;/error.log debug;/\" ".$configFile);
                            $log_path = "/var/log/nginx/".$ee_domain.".error.log ".$ee_site_webroot."/logs/*.log";
                        } else {
                            $log_path = "/var/log/nginx/".$ee_domain.".error.log ".$ee_site_webroot."/logs/*.log";
                            EE::success("Debug is already enabled for the given site");
                        }
                    }
                }
            }
            EE_Service::reload_service('nginx');
            var_dump($log_path);
		    if( !empty($debug['interactive']) && !empty($log_path) ){
                EE::tail_logs($log_path);
            }
		} elseif ( 'off' === $debug['nginx'] ) {
		    if( empty( $debug['sitename'] ) ){
                if ( grep_string( '/etc/nginx/nginx.conf', 'debug_connection' ) ) {
                    EE::success( "Disabling Nginx debug connections" );
                    EE::exec_cmd( "sed -i \"/debug_connection.*/d\" /etc/nginx/nginx.conf" );
                } else {
                    EE::success( "Nginx debug connection already disabled" );
                }
            } else {
                if(is_site_exist($debug['sitename'])){
                    $configFile = "/etc/nginx/sites-available/".$debug['sitename'];
                    if(ee_file_exists($configFile)){
                        if(grep_string($configFile,"error.log debug")){
                            EE::exec_cmd("sed -i \"s/error.log debug;/error.log;/\" ".$configFile);
                        } else {
                            EE::success("Debug is already disabled for the given site");
                        }
                    }
                }
            }
            EE_Service::reload_service('nginx');
		}
	}

	public function debug_php() {
		// """Start/Stop PHP debug"""
		//todo:

	}

	public function debug_mysql( $_argc ) {
		//"""Start/Stop MySQL debug"""
		$debug = $_argc;
		if ( 'on' === $debug['mysql'] && empty( $debug['sitename'] ) ) {
			if ( ! EE::exec_cmd( "mysql -e \"show variables like 'slow_query_log';\" | grep ON" ) ) {
				EE::success( "Setting up MySQL slow log" );
				EE_MySql::execute( "set global slow_query_log = 'ON';" );
				EE_MySql::execute( "set global slow_query_log_file = '/var/log/mysql/mysql-slow.log';" );
				EE_MySql::execute( "set global long_query_time = 2;" );
				EE_MySql::execute( "set global log_queries_not_using_indexes = 'ON';" );
			} else {
				EE::success( "MySQL slow log is already enabled" );
			}

		} elseif ( 'off' === $debug['mysql'] && empty( $debug['sitename'] ) ) {
			if ( EE::exec_cmd( "mysql -e \"show variables like 'slow_query_log';\" | grep ON" ) ) {
				EE::success( "Disabling MySQL slow log" );
				EE_MySql::execute( "set global slow_query_log = 'OFF';" );
				EE_MySql::execute( "set global slow_query_log_file = '/var/log/mysql/mysql-slow.log';" );
				EE_MySql::execute( "set global long_query_time = 10;" );
				EE_MySql::execute( "set global log_queries_not_using_indexes = 'OFF';" );
			} else {
				EE::success( "MySQL slow log already disabled" );
			}
		}

		EE_Service::restart_service('mysql');
	}

	public function debug_wp( $_argc ) {
		//Start/Stop WordPress debug

		$debug = $_argc;
		if ( 'on' === $debug['wp'] && ! empty( $debug['sitename'] ) ) {
			$wp_config = EE_WEBROOT . $debug['sitename'] . "/wp-config.php";
			$webroot   = EE_WEBROOT . $debug['sitename'];

			if ( ! is_file( $wp_config ) ) {
				$wp_config = EE_WEBROOT . $debug['sitename'] . "/htdocs/wp-config.php";
			}

			if ( ! is_file( $wp_config ) ) {
				if ( ! EE::exec_cmd( "grep \"'WP_DEBUG'\" " . $wp_config . " | grep true" ) ) {
					EE::success( "Starting WordPress debug" );
					ee_file_touch( $webroot . "/htdocs/wp-content/debug.log" );
					ee_file_chown( $webroot . "/htdocs/wp-content/debug.log", EE_PHP_USER );
					EE::exec_cmd( "sed -i \"s/define('WP_DEBUG'.*/define('WP_DEBUG', true);
																\ndefine('WP_DEBUG_DISPLAY', false);
																\ndefine('WP_DEBUG_LOG', true);
																\ndefine('SAVEQUERIES', true);/\" " . $wp_config . "" );
					EE::exec_cmd( "cd " . $webroot . "/htdocs/ && wp plugin --allow-root install developer query-monitor" );
					EE::exec_cmd( "chown -R " . EE_PHP_USER . ":" . " " . $webroot . "/htdocs/wp-content/plugins" );
					EE::success( "Log Enabled: " . $webroot . "/htdocs/wp-content/debug.log" );
				}
			} else {
				EE::error( "Unable to find wp-config.php for site", false );
			}
		} elseif ( 'off' === $debug['wp'] && ! empty( $debug['sitename'] ) ) {
			$wp_config = EE_WEBROOT . $debug['sitename'] . "/wp-config.php";
			$webroot   = EE_WEBROOT . $debug['sitename'];

			if ( ! is_file( $wp_config ) ) {
				$wp_config = EE_WEBROOT . $debug['sitename'] . "/htdocs/wp-config.php";
			}

			if ( ! is_file( $wp_config ) ) {
				if ( EE::exec_cmd( "grep \"'WP_DEBUG'\" " . $wp_config . " | grep true" ) ) {
					EE::success( "Disabling WordPress debug" );
					EE::exec_cmd( "sed -i \"s/define('WP_DEBUG', true);/define('WP_DEBUG', false);/\" " . $wp_config . "" );
					EE::exec_cmd( "sed -i \"/define('WP_DEBUG_DISPLAY', false);/d\" " . $wp_config . "" );
					EE::exec_cmd( "sed -i \"/define('WP_DEBUG_LOG', true);/d\" " . $wp_config . "" );
					EE::exec_cmd( "sed -i \"/define('SAVEQUERIES', true);/d\" " . $wp_config . "" );
				} else {
					EE::error( "WordPress debug already disabled", false );
				}
			}

		} else {
			EE::error( "Missing argument site name" );
		}
	}

	/**
	 * Enable Debug Mode.
	 *
	 * ## OPTIONS
	 *
	 * [<sitename>]
	 * : Name of the site to debug.
	 *
	 * [--nginx]
	 * : Enable nginx debug mode
	 * ---
	 * default: on
	 * options:
	 *   - on
	 *   - off
	 *
	 * [--mysql]
	 * : Enable Mysql debug mode
	 * ---
	 * default: on
	 * options:
	 *   - on
	 *   - off
	 *
	 * [--wp]
	 * : Enable wordpress debug mode
	 * ---
	 * default: on
	 * options:
	 *   - on
	 *   - off
	 *
     * [--interactive]
     * : Gives realtime logs
     * default: off
     * options:
     *   - on
     *   - off
     *
	 * ## EXAMPLES
	 *
	 *      # Enable Debug Mode.
	 *      $ ee debug --nginx
	 *      $ ee debug example.com --wp
	 *
	 */
	public function __invoke( $args, $assoc_args ) {
		$_argc = array();
		$_argc = array_merge( $_argc, $assoc_args );
		if ( ! empty( $args ) ) {
			$_argc['sitename'] = $args[0];
		}

		if ( ! empty( $_argc[ 'nginx' ] ) ) {
			self::debug_nginx( $_argc );
		}

		if ( ! empty( $_argc[ 'mysql' ] ) ) {
			if ( 'localhost' === EE_Variables::get_ee_mysql_host() ) {
				self::debug_mysql( $_argc );
			} else {
				EE::warning( "Remote MySQL found, EasyEngine will not enable remote debug" );
			}
		}

		if ( ! empty( $_argc[ 'wp' ] ) ) {
			self::debug_wp( $_argc );
		}

	}

}

EE::add_command( 'debug', 'Debug_Command' );

