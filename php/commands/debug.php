<?php
/**
 * These commands are used for server level debugging.
 *
 * @package EasyEngine
 * @subpackage EasyEngine/Commands
 */

use \EE\Utils;
use \EE\Dispatcher;
use NoiseLabs\ToolKit\ConfigParser\ConfigParser;

/**
 * Debug_Command are used for server level debugging.
 */
class Debug_Command extends EE_Command {

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
	 * [--php]
	 * : Enable PHP debug mode
	 * ---
	 * default: on
	 * options:
	 *   - on
	 *   - off
	 *
	 * [--fpm]
	 * : Enable PHP5-FPM debug mode
	 * ---
	 * default: on
	 * options:
	 *   - on
	 *   - off
	 *
	 * [--version]
	 * : Enable PHP5-version debug mode
	 *
	 * [--rewrite]
	 * : Debug Nginx rewrite rule
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
	 * [--all]
	 * : Enable all debug mode
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
	 * @param array $args invoke argument.
	 * @param array $assoc_args invoke argument.
	 */
	public function __invoke( $args, $assoc_args ) {

		$_argc = array();
		$_argc = array_merge( $_argc, $assoc_args );

		if ( ! empty( $args ) ) {
			$_argc['sitename'] = $args[0];
		}

		if ( ! empty( $_argc['nginx'] ) ) {
			self::debug_nginx( $_argc );
		}

		if ( ! empty( $_argc['mysql'] ) ) {
			if ( 'localhost' === EE_Variables::get_ee_mysql_host() ) {
				self::debug_mysql( $_argc );
			} else {
				EE::warning( 'Remote MySQL found, EasyEngine will not enable remote debug' );
			}
		}

		if ( ! empty( $_argc['wp'] ) ) {
			self::debug_wp( $_argc );
		}

		if ( ! empty( $_argc['fpm'] ) && ! empty( $_argc['version'] ) ) {
			self::debug_fpm7( $_argc );
		} elseif ( ! empty( $_argc['fpm'] ) ) {
			self::debug_fpm( $_argc );
		}

		if ( ! empty( $_argc['rewrite'] ) ) {
			self::debug_rewrite( $_argc );
		}
	}

	/**
	 * Function to debug nginx.
	 *
	 * @param array $_argc command parameter.
	 */
	public function debug_nginx( $_argc ) {
		// Start or Stop Nginx debug.
		$debug         = $_argc;
		$debug_address = '0.0.0.0/0';
		if ( isset( $debug['sitename'] ) ) {
			$site_name = $debug['sitename'];
			$ee_domain = EE_Utils::validate_domain( $site_name );
			$ee_site_info = get_site_info( $site_name );
			$ee_site_webroot = $ee_site_info['site_path'];
		}
		if ( 'on' === $debug['nginx'] ) {
		    $log_path = '';
		    if ( empty( $ee_domain ) ) {
				if ( ! grep_string( '/etc/nginx/nginx.conf', 'debug_connection' ) ) {
					EE::success( 'Setting up Nginx debug connection for 0.0.0.0/0' );
					EE::exec_cmd( "sed -i 's@events {@events {\\n\\tdebug_connection " . $debug_address . ";@' /etc/nginx/nginx.conf" );
					$log_path = '/var/log/nginx/*.error.log';
				} else {
					EE::success( 'Nginx debug connection already enabled' );
					$log_path = '/var/log/nginx/*.error.log';
				}
			} else {
				if ( is_site_exist( $ee_domain ) ) {
					$config_file = '/etc/nginx/sites-available/' . $ee_domain;
					if ( ee_file_exists( $config_file ) ) {
						if ( ! grep_string( $config_file,'error.log debug' ) ) {
							EE::exec_cmd( 'sed -i "s/error.log;/error.log debug;/" ' . $config_file );
							$log_path = '/var/log/nginx/' . $ee_domain . '.error.log ' . $ee_site_webroot . '/logs/*.log';
						} else {
							$log_path = '/var/log/nginx/' . $ee_domain . '.error.log ' . $ee_site_webroot . '/logs/*.log';
							EE::success( 'Debug is already enabled for the given site' );
						}
					}
				}
			}
			EE_Service::reload_service( 'nginx' );
		    if ( ! empty( $debug['interactive'] ) && 'on' === $debug['interactive'] && ! empty( $log_path ) ) {
				EE::tail_logs( $log_path );
			}
		} elseif ( 'off' === $debug['nginx'] ) {
		    if ( empty( $debug['sitename'] ) ) {
				if ( grep_string( '/etc/nginx/nginx.conf', 'debug_connection' ) ) {
					EE::success( 'Disabling Nginx debug connections' );
					EE::exec_cmd( 'sed -i "/debug_connection.*/d" /etc/nginx/nginx.conf' );
				} else {
					EE::success( 'Nginx debug connection already disabled' );
				}
			} else {
				if ( is_site_exist( $debug['sitename'] ) ) {
					$config_file = '/etc/nginx/sites-available/' . $debug['sitename'];
					if ( ee_file_exists( $config_file ) ) {
						if ( grep_string( $config_file,'error.log debug' ) ) {
							EE::exec_cmd( 'sed -i "s/error.log debug;/error.log;/" ' . $config_file );
						} else {
							EE::success( 'Debug is already disabled for the given site' );
						}
					}
				}
			}
			EE_Service::reload_service( 'nginx' );
		} else {
			EE::error( 'Missing argument value on/off' );
		}
	}

	/**
	 * Function to debug fpm.
	 *
	 * @param array $args command parameter.
	 */
	public function debug_fpm( $args ) {
		// Start/Stop PHP5-FPM7 debug.
		$debug = $args;
		$php_version_folder = ( EE_OS::ee_platform_codename() === 'trusty' or EE_OS::ee_platform_codename() === 'xenial' ) ? 'php/5.6' : 'php5';
		$fpm_conf_path = '/etc/' . $php_version_folder . '/fpm/php-fpm.conf';
		if ( isset( $debug['fpm'] ) && 'on' === $debug['fpm'] && empty( $debug['sitename'] ) ) {
			if ( EE::exec_cmd( 'grep "log_level = debug" ' . $fpm_conf_path ) ) {
				EE::success( 'Enabling FPM debug' );
				// Created ConfigParser class instance.
				$config = new ConfigParser();
				$config->read( $fpm_conf_path );
				$config->removeOption( 'global', 'include' );
				$config->set( 'global','include', '/etc/' . $php_version_folder . '/fpm/pool.d/*.conf' );
				$config->set( 'global','log_level', 'debug' );
				$config->write( $fpm_conf_path );
			} else {
				EE::info( 'FPM debug is already enabled' );
			}
		} elseif ( isset( $debug['fpm'] ) && 'off' === $debug['fpm'] && empty( $debug['sitename'] ) ) {
			if ( ! EE::exec_cmd( 'grep "log_level = debug" ' . $fpm_conf_path ) ) {
				EE::success( 'Disabling FPM debug' );
				// Created ConfigParser class instance.
				$config = new ConfigParser();
				$php_version_folder = ( EE_OS::ee_platform_codename() === 'trusty' or EE_OS::ee_platform_codename() === 'xenial' ) ? 'php/5.6' : 'php5';
				$config->read( $fpm_conf_path );
				$config->set( 'global','log_level', 'notice' );
				$config->set( 'global','include', '/etc/' . $php_version_folder . '/fpm/pool.d/*.conf' );
				$config->write( $fpm_conf_path );
			} else {
				EE::info( 'FPM debug is already disabled' );
			}
		} else {
			EE::error( 'Missing argument value on/off' );
		}
	}

	/**
	 * Function to debug fpm7.
	 *
	 * @param array $args command parameter.
	 */
	public function debug_fpm7( $args ) {
		// Start/Stop PHP5-FPM7 debug.
		$debug = $args;
		if ( 'on' === $debug['fpm'] && empty( $debug['sitename'] ) ) {
			if ( EE::exec_cmd( 'grep "log_level = debug" /etc/php/7.0/fpm/php-fpm.conf' ) ) {
				EE::success( 'Enabling FPM7 debug' );
				// Created ConfigParser class instance.
				$config = new ConfigParser();
				$config->read( '/etc/php/7.0/fpm/php-fpm.conf' );
				$config->removeOption( 'global', 'include' );
				$config->set( 'global','include', '/etc/php/7.0/fpm/pool.d/*.conf' );
				$config->set( 'global','log_level', 'debug' );
				$config->write( '/etc/php/7.0/fpm/php-fpm.conf' );
			} else {
				EE::info( 'FPM7 debug is already enabled' );
			}
		} elseif ( 'off' === $debug['fpm'] && empty( $debug['sitename'] ) ) {
			if ( ! EE::exec_cmd( 'grep "log_level = debug" /etc/php/7.0/fpm/php-fpm.conf' ) ) {
				EE::success( 'Disabling FPM7 debug' );
				// Created ConfigParser class instance.
				$config = new ConfigParser();
				$php_version_folder = ( EE_OS::ee_platform_codename() === 'trusty' or EE_OS::ee_platform_codename() === 'xenial' ) ? 'php/5.6' : 'php5';
				$config->read( '/etc/php/7.0/fpm/php-fpm.conf' );
				$config->removeOption( 'global', 'include' );
				$config->set( 'global','include', '/etc/php/7.0/fpm/pool.d/*.conf' );
				$config->set( 'global','log_level', 'notice' );
				$config->write( '/etc/php/7.0/fpm/php-fpm.conf' );
			} else {
				EE::success( 'FPM7 debug is already disabled' );
			}
		} else {
			EE::error( 'Missing argument value on/off' );
		}

	}

	/**
	 * Function to debug wp.
	 *
	 * @param array $_argc command parameter.
	 */
	public function debug_rewrite( $_argc ) {
		$debug = $_argc;
		if ( isset( $debug['sitename'] ) ) {
			$site_name = $debug['sitename'];
			$ee_domain = EE_Utils::validate_domain( $site_name );
			$ee_site_info = get_site_info( $site_name );
			$ee_site_webroot = $ee_site_info['site_path'];
		}
		if ( 'on' === $debug['rewrite'] ) {
			if ( empty( $ee_domain ) ) {
				if ( ! grep_string( '/etc/nginx/nginx.conf', 'rewrite_log on' ) ) {
					EE::success( 'Setting up Nginx rewrite logs' );
					EE::exec_cmd( "sed -i '/http {/a \\\\t rewrite_log on;' /etc/nginx/nginx.conf" );
				} else {
					EE::success( 'Nginx rewrite logs already enabled' );
				}
			} else {
				// If domain exist.
				if ( is_site_exist( $ee_domain ) ) {
					$config_file = '/etc/nginx/sites-available/' . $ee_domain;
					if ( ee_file_exists( $config_file ) ) {
						if ( ! grep_string( $config_file, 'rewrite_log on' ) ) {
							EE::info( 'Setting up Nginx rewrite logs' );
							EE::exec_cmd( 'sed -i "/access_log /i \\\\\\t rewrite_log on;" ' . $config_file );

						} else {
							EE::info( 'Debug is already enabled for the given site' );
						}
					}
				} else {
					EE::info( 'Site not exist.' );
				}
			}
		} elseif ( 'off' === $debug['rewrite'] ) {
			if ( empty( $ee_domain ) ) {
				if ( grep_string( '/etc/nginx/nginx.conf', 'rewrite_log on' ) ) {
						EE::info( 'Disabling Nginx rewrite logs' );
						EE::exec_cmd( 'sed -i "/rewrite_log.*/d" /etc/nginx/nginx.conf' );
				} else {
					EE::info( 'Nginx rewrite logs already disabled' );
				}
			} else {
				$config_file = '/etc/nginx/sites-available/' . $ee_domain;
				if ( ee_file_exists( $config_file ) ) {
					if ( grep_string( $config_file, 'rewrite_log on' ) ) {
						EE::info( 'Disabling Nginx rewrite logs' );
						EE::exec_cmd( 'sed -i "/rewrite_log.*/d" ' . $config_file );
					} else {
						EE::info( 'Nginx rewrite logs already disabled for given site' );
					}
				}
			}
		} else {
			EE::error( 'Missing argument value on/off' );
		}
	}

	/**
	 * Function to debug php
	 *
	 * @param array $_argc command parameter.
	 */
	public function debug_php( $_argc ) {
		// Start/Stop PHP debug.
		if ( 'on' === $debug['php'] && empty( $debug['sitename'] ) ) {
			if ( ! EE::exec_cmd( 'sed -n \"/upstream php {/,/}/p \" /etc/nginx/ conf.d/upstream.conf | grep 9001' ) ) {
				EE::success( 'Enabling PHP debug' );
			} else {
				EE::info( 'PHP debug is already enabled' );
			}
		} elseif ( 'off' === $debug['fpm'] && empty( $debug['sitename'] ) ) {
			if ( EE::exec_cmd( 'sed -n \"/upstream php {/,/}/p \" /etc/nginx/ conf.d/upstream.conf | grep 9001' ) ) {
				EE::success( 'Disabling PHP debug' );
			} else {
				EE::success( 'PHP debug is already disabled' );
			}
		} else {
			EE::error( 'Missing argument value on/off' );
		}
	}

	/**
	 * Function to debug mysql
	 *
	 * @param array $_argc command parameter.
	 */
	public function debug_mysql( $_argc ) {
		// Start/Stop MySQL debug.
		$debug = $_argc;
		if ( 'on' === $debug['mysql'] && empty( $debug['sitename'] ) ) {
			if ( ! EE::exec_cmd( "mysql -e \"show variables like 'slow_query_log';\" | grep ON" ) ) {
				EE::success( 'Setting up MySQL slow log' );
				EE_MySql::execute( "set global slow_query_log = 'ON';" );
				EE_MySql::execute( "set global slow_query_log_file = '/var/log/mysql/mysql-slow.log';" );
				EE_MySql::execute( 'set global long_query_time = 2;' );
				EE_MySql::execute( "set global log_queries_not_using_indexes = 'ON';" );
			} else {
				EE::success( 'MySQL slow log is already enabled' );
			}
			EE_Service::restart_service( 'mysql' );
		} elseif ( 'off' === $debug['mysql'] && empty( $debug['sitename'] ) ) {
			if ( EE::exec_cmd( "mysql -e \"show variables like 'slow_query_log';\" | grep ON" ) ) {
				EE::success( 'Disabling MySQL slow log' );
				EE_MySql::execute( "set global slow_query_log = 'OFF';" );
				EE_MySql::execute( "set global slow_query_log_file = '/var/log/mysql/mysql-slow.log';" );
				EE_MySql::execute( 'set global long_query_time = 10;' );
				EE_MySql::execute( "set global log_queries_not_using_indexes = 'OFF';" );
			} else {
				EE::success( 'MySQL slow log already disabled' );
			}
			EE_Service::restart_service( 'mysql' );
		} else {
			EE::error( 'Missing argument value on/off' );
		}
	}

	/**
	 * Function to debug wp.
	 *
	 * @param array $_argc command parameter.
	 */
	public function debug_wp( $_argc ) {
		// Start/Stop WordPress debug.
		$debug = $_argc;
		if ( empty( $debug['sitename'] ) ) {
			EE::error( 'Site Name is missing', true );
		}
		if ( 'on' === $debug['wp'] && ! empty( $debug['sitename'] ) ) {
			$wp_config = EE_WEBROOT . $debug['sitename'] . '/wp-config.php';
			$webroot   = EE_WEBROOT . $debug['sitename'];
			if ( ! is_file( $wp_config ) ) {
				$wp_config = EE_WEBROOT . $debug['sitename'] . '/htdocs/wp-config.php';
			}
			if ( is_file( $wp_config ) ) {
				if ( 1 === EE::exec_cmd( "grep \"'WP_DEBUG'\" " . $wp_config . ' | grep true' ) ) {
					EE::success( 'Starting WordPress debug' );
					ee_file_touch( $webroot . '/htdocs/wp-content/debug.log' );
					ee_file_chown( $webroot . '/htdocs/wp-content/debug.log', EE_PHP_USER );
					EE::exec_cmd( "sed -i \"s@define('WP_DEBUG'.*@define('WP_DEBUG', true); \\n define('WP_DEBUG_DISPLAY', false);\\n define('WP_DEBUG_LOG', true);\\n define('SAVEQUERIES', true);@g\" " . $wp_config );
					EE::exec_cmd( 'cd ' . $webroot . '/htdocs/ && wp plugin --allow-root install developer query-monitor' );
					EE::exec_cmd( 'chown -R ' . EE_PHP_USER . ': ' . $webroot . '/htdocs/wp-content/plugins' );
					EE::success( 'Log Enabled: ' . $webroot . '/htdocs/wp-content/debug.log' );
				} else {
					EE::info( 'WP_DEBUG is already on in wp-config.php file', false );
				}
			} else {
				EE::error( 'Unable to find wp-config.php for site', false );
			}
		} elseif ( 'off' === $debug['wp'] && ! empty( $debug['sitename'] ) ) {
			$wp_config = EE_WEBROOT . $debug['sitename'] . '/wp-config.php';
			$webroot   = EE_WEBROOT . $debug['sitename'];
			if ( ! is_file( $wp_config ) ) {
				$wp_config = EE_WEBROOT . $debug['sitename'] . '/htdocs/wp-config.php';
			}

			if ( is_file( $wp_config ) ) {
				if ( 0 === EE::exec_cmd( "grep \"'WP_DEBUG'\" " . $wp_config . ' | grep true' ) ) {
					EE::success( 'Disabling WordPress debug' );
					EE::exec_cmd( "sed -i \"s/define('WP_DEBUG', true);/define('WP_DEBUG', false);/\" " . $wp_config );
					EE::exec_cmd( "sed -i \"/define('WP_DEBUG_DISPLAY', false);/d\" " . $wp_config );
					EE::exec_cmd( "sed -i \"/define('WP_DEBUG_LOG', true);/d\" " . $wp_config );
					EE::exec_cmd( "sed -i \"/define('SAVEQUERIES', true);/d\" " . $wp_config );
				} else {
					EE::info( 'WordPress debug already disabled', false );
				}
			}
		} else {
			EE::error( 'Missing argument value on/off' );
		}

	}

}

EE::add_command( 'debug', 'Debug_Command' );

