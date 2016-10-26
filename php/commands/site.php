<?php

/**
 * Manage sites.
 *
 * ## EXAMPLES
 *
 *     # Create site
 *     $ ee site create example.com
 *     Success: Created example.com site.
 *
 *     # Update site
 *     $ ee site update example.com
 *     Success: Updated example.com site.
 *
 *     # Delete site
 *     $ ee site delete example.com
 *     Success: Deleted example.com site.
 *
 * @package easyengine
 */
class Site_Command extends EE_Command {

	/**
	 * Create site.
	 *
	 * ## OPTIONS
	 *
	 * [<name>]
	 * : Name of the site to create.
	 *
	 * [--type=<types>]
	 * : Type for create site.
	 *
	 * [--cache=<cache>]
	 * : Cache for site.
	 *
	 * [--user=<username>]
	 * : Username for WordPress admin.
	 *
	 * [--email=<email>]
	 * : Email id for WordPress admin.
	 *
	 * [--pass=<pass>]
	 * : Password for WordPress admin.
	 *
	 * [--ip=<ip>]
	 * : Proxy ip address for proxy site.
	 *
	 * [--port=<port>]
	 * : Port no for porxy site.
	 *
	 * [--letsencrypt]
	 * : Encrypt site.
	 *
	 * [--experimental]
	 * : For Experiment beta features.
	 *
	 *
	 *
	 * ## EXAMPLES
	 *
	 *      # Create site.
	 *      $ ee site create example.com
	 *
	 */
	public function create( $args, $assoc_args ) {

		$site_name = empty( $args[0] ) ? '' : $args[0];

		while ( empty( $site_name ) ) {
			$value = EE::input_value( "Enter site name :" );
			if ( $value ) {
				$site_name = $value;
			}
		}
		$ee_www_domain = EE_Utils::validate_domain( $site_name, false );
		$ee_domain     = EE_Utils::validate_domain( $site_name );

		if ( empty( $ee_domain ) ) {
			EE::error( 'Invalid domain name, Provide valid domain name' );
		}
		if ( is_site_exist( $ee_domain ) ) {
			EE::error( "Site {$ee_domain} already exists" );
		} else if ( ee_file_exists( EE_NGINX_SITE_AVAIL_DIR . $ee_domain ) ) {
			EE::error( "Nginx configuration /etc/nginx/sites-available/{$ee_domain} already exists" );
		}
		$ee_site_webroot = EE_Variables::get_ee_webroot() . $ee_domain;
		$registered_cmd  = array(
			'html',
			'php',
			'php7',
			'mysql',
			'wp',
			'wpsubdir',
			'wpsubdomain',
			'w3tc',
			'wpfc',
			'wpsc',
			'wpredis',
			'hhvm',
			'pagespeed',
			'le',
			'letsencrypt',
			'user',
			'email',
			'pass',
			'proxy',
			'experimental',
		);

		$data               = array();
		$data['site_name']  = $ee_domain;
		$data['www_domain'] = $ee_www_domain;
		$data['webroot']    = $ee_site_webroot;
		$stype              = empty( $assoc_args['type'] ) ? 'html' : $assoc_args['type'];
		$data['site_type']  = $stype;
		$cache              = empty( $assoc_args['cache'] ) ? 'basic' : $assoc_args['cache'];
		$data['cache_type'] = $cache;
		$data['site_path']  = $ee_site_webroot;
		$letsencrypt        = empty( $assoc_args['letsencrypt'] ) ? false : true;
		$experimental       = empty( $assoc_args['experimental'] ) ? false : true;

		if ( ! empty( $stype ) ) {
			if ( in_array( $stype, $registered_cmd ) ) {
				if ( 'proxy' == $stype ) {
					$proxyinfo = $assoc_args['ip'];
					if ( strpos( $proxyinfo, ':' ) !== false ) {
						$proxyinfo = explode( ':', $proxyinfo );
						$host      = $proxyinfo[0];
						$port      = ( strlen( $proxyinfo[1] ) < 2 ) ? '80' : $proxyinfo[1];
					} else {
						$host = $assoc_args['ip'];
						$port = $assoc_args['port'];
					}
					$data['proxy']   = true;
					$data['host']    = $host;
					$data['port']    = $port;
					$ee_site_webroot = "";
				} else if ( 'php7' == $stype ) {
					$data['static']    = false;
					$data['basic']     = false;
					$data['php7']      = true;
					$data['wp']        = false;
					$data['w3tc']      = false;
					$data['wpfc']      = false;
					$data['wpsc']      = false;
					$data['multisite'] = false;
					$data['wpsubdir']  = false;
					$data['basic']     = true;
				} else if ( in_array( $stype, array( 'html', 'php' ) ) ) {
					$data['static']    = true;
					$data['basic']     = false;
					$data['php7']      = false;
					$data['wp']        = false;
					$data['w3tc']      = false;
					$data['wpfc']      = false;
					$data['wpsc']      = false;
					$data['multisite'] = false;
					$data['wpsubdir']  = false;
					if ( 'php' === $stype ) {
						$data['static'] = false;
						$data['basic']  = true;
					}
				} else if ( in_array( $stype, array( 'mysql', 'wp', 'wpsubdir', 'wpsubdomain' ) ) ) {
					$data['static']     = false;
					$data['basic']      = true;
					$data['wp']         = false;
					$data['w3tc']       = false;
					$data['wpfc']       = false;
					$data['wpsc']       = false;
					$data['wpredis']    = false;
					$data['multisite']  = false;
					$data['wpsubdir']   = false;
					$data['ee_db_name'] = '';
					$data['ee_db_user'] = '';
					$data['ee_db_pass'] = '';
					$data['ee_db_host'] = '';
					if ( in_array( $stype, array( 'wp', 'wpsubdir', 'wpsubdomain' ) ) ) {
						$data['wp']       = true;
						$data['basic']    = false;
						$data['cache']    = true;
						$data['wp-user']  = empty( $assoc_args['user'] ) ? '' : $assoc_args['user'];
						$data['wp-email'] = empty( $assoc_args['email'] ) ? '' : $assoc_args['email'];
						$data['wp-pass']  = empty( $assoc_args['pass'] ) ? '' : $assoc_args['pass'];
						if ( in_array( $stype, array( 'wpsubdir', 'wpsubdomain' ) ) ) {
							$data['multisite'] = true;
							if ( 'wpsubdir' == $stype ) {
								$data['wpsubdir'] = true;
							}
						}
					}
				}

				if ( ! in_array( $cache, array( 'w3tc', 'wpfc', 'wpsc', 'wpredis', 'hhvm' ) ) ) {
					$data['basic'] = true;
				}
				$ee_auth = site_package_check( $stype );

				try {
					pre_run_checks();
				} catch ( Exception $e ) {
					EE::debug( $e->getMessage() );
					EE::error( 'NGINX configuration check failed.' );
				}

				try {
					try {
						setup_domain( $data );
						//				hashbucket();
					} catch ( Exception $e ) {
						EE::log( 'Oops Something went wrong !!' );
						EE::log( 'Calling cleanup actions ...' );
						do_cleanup_action( $data );
						EE::debug( $e->getMessage() );
						EE::error( 'Check logs for reason `tail /var/log/ee/ee.log` & Try Again!!!' );
					}

					if ( isset( $data['proxy'] ) && $data['proxy'] ) {
						add_new_site( $data );
						$reload_nginx = EE_Service::reload_service( 'nginx' );
						if ( ! $reload_nginx ) {
							EE::log( 'Oops Something went wrong !!' );
							EE::log( 'Calling cleanup actions ...' );
							do_cleanup_action( $data );
							EE::error( 'Service nginx reload failed. check issues with `nginx -t` command.' );
							EE::error( 'Check logs for reason `tail /var/log/ee/ee.log` & Try Again!!!' );
						}
						if ( ! empty( $ee_auth ) ) {
							foreach ( $ee_auth as $msg ) {
								EE::log( $msg );
							}
						}
						EE::success( 'Successfully created site http://' . $ee_domain );
					}

					$data['php_version'] = "5.6";
					if ( ! empty( $data['php7'] ) && $data['php7'] ) {
						$data['php_version'] = "7.0";
					}
					add_new_site( $data );
					// Setup database for MySQL site.
					if ( isset( $data['ee_db_name'] ) && ! $data['wp'] ) {
						try {
							$data = setup_database( $data );
							update_site( $data, array( 'site_name' => $data['site_name'] ) );
						} catch ( Exception $e ) {
							EE::debug( $e->getMessage() );
							EE::log( "Oops Something went wrong !!" );
							EE::log( "Calling cleanup actions ..." );
							do_cleanup_action( $data );
							delete_site( array( 'site_name' => $ee_domain ) );
						}

						try {
							$ee_db_config  = $ee_site_webroot . "/ee-config.php";
							$ee_db_content = "<?php \ndefine('DB_NAME', '{$data['ee_db_name']}');" . "\ndefine('DB_USER', '{$data['ee_db_user']}'); " . "\ndefine('DB_PASSWORD', '{$data['ee_db_pass']}');" . "\ndefine('DB_HOST', '{$data['ee_db_host']}');\n?>";
							ee_file_dump( $ee_db_config, $ee_db_content );
							$stype = 'mysql';
						} catch ( Exception $e ) {
							EE::debug( $e->getMessage() );
							EE::debug( "Error occured while generating ee-config.php" );
							EE::log( "Oops Something went wrong !!" );
							EE::log( "Calling cleanup actions ..." );
							do_cleanup_action( $data );
							delete_site( array( 'site_name' => $ee_domain ) );
							EE::error( "Check logs for reason `tail /var/log/ee/ee.log` & Try Again!!!" );
						}
					}

					if ( $data["wp"] ) {
						try {
							$ee_wp_creds = setup_wordpress( $data );
						} catch ( Exception $e ) {
							EE::debug( $e->getMessage() );
							EE::log( "Oops Something went wrong !!" );
							EE::log( "Calling cleanup actions ..." );
							do_cleanup_action( $data );
							delete_site( array( 'site_name' => $ee_domain ) );
							EE::error( "Check logs for reason `tail /var/log/ee/ee.log` & Try Again!!!" );
						}
					}

					if ( ! EE_Service::reload_service( 'nginx' ) ) {
						EE::log( "Oops Something went wrong !!" );
						EE::log( "Calling cleanup actions ..." );
						do_cleanup_action( $data );
						delete_site( array( 'site_name' => $ee_domain ) );
						EE::log( "service nginx reload failed. check issues with `nginx -t` command." );
						EE::error( "Check logs for reason `tail /var/log/ee/ee.log` & Try Again!!!" );
					}

					EE_Git::add( array( "/etc/nginx" ), "{$ee_www_domain} created with {$stype} {$cache}" );

					// Setup Permissions for webroot.
					try {
						set_webroot_permissions( $data['webroot'] );
					} catch ( Exception $e ) {
						EE::debug( $e->getMessage() );
						EE::log( "Oops Something went wrong !!" );
						EE::log( "Calling cleanup actions ..." );
						do_cleanup_action( $data );
						delete_site( array( 'site_name' => $ee_domain ) );
						EE::error( "Check logs for reason `tail /var/log/ee/ee.log` & Try Again!!!" );
					}

					if ( ! empty( $ee_auth ) ) {
						foreach ( $ee_auth as $msg ) {
							EE::log( $msg );
						}
					}

					if ( $data['wp'] ) {
						if ( ! empty( $ee_wp_creds ) ) {
							EE::log( "WordPress admin user : {$ee_wp_creds['wp_user']}" );
							EE::log( "WordPress admin user password : {$ee_wp_creds['wp_pass']}" );
							display_cache_settings( $data );
						} else {
							EE::debug( "Credentials could not setup." );
							EE::debug( "WordPress Site couldn't be setup." );
						}
					}

					EE::success( "Successfully created site http://{$ee_domain}" );
				} catch ( Exception $e ) {
					EE::error( "Check logs for reason `tail /var/log/ee/ee.log` & Try Again!!!" );
				}

				if ( $letsencrypt ) {
					if ( $experimental ) {
						if ( 'wpsubdomain' === $stype ) {
							EE::warning( "Wildcard domains are not supported in Lets Encrypt.\nWP SUBDOMAIN site will get SSL for primary site only." );
						}
						EE::log( "Letsencrypt is currently in beta phase. \nDo you wish to enable SSl now for {$ee_domain}?" );
						$check_prompt = EE::input_value( "Type \"y\" to continue [n]:" );
						if ( 'y' !== strtolower( $check_prompt ) ) {
							$data['letsencrypt'] = false;
							$letsencrypt         = false;
						} else {
							$data['letsencrypt'] = true;
							$letsencrypt         = true;
						}
					} else {
						$data['letsencrypt'] = true;
						$letsencrypt         = true;
					}


					if ( $data['letsencrypt'] ) {
						setup_lets_encrypt( $ee_domain );
						https_redirect( $ee_domain );
						EE::log( "Creating Cron Job for cert auto-renewal" );
						EE_Cron::set_cron_weekly( 'ee site update --le=renew --all 2> /dev/null', 'Renew all letsencrypt SSL cert. Set by EasyEngine' );

						if ( EE_Service::reload_service( 'nginx' ) ) {
							EE::log( "service nginx reload failed. check issues with `nginx -t` command" );
						}

						EE::log( "Congratulations! Successfully Configured SSl for Site https://{$ee_domain}" );

						$ee_ssl_expiration_days = EE_Ssl::get_expiration_days( $ee_domain );

						if ( $ee_ssl_expiration_days > 0 ) {
							EE::log( "Your cert will expire within {$ee_ssl_expiration_days} days." );
						} else {
							EE::log( "Your cert already EXPIRED ! Please renew soon." );
						}

						EE_Git::add( array( "{$ee_site_webroot}/conf/nginx" ), "Adding letsencrypts config of site: {$ee_domain}" );
						update_site( $data, array( 'site_name' => $data['site_name'] ) );
					} else {
						EE::log( "Not using Let's encrypt for Site http://{$ee_domain}" );
					}
				}
			} else {
				//TODO: we will add hook for other packages. i.e do_action('create_site',$stype);
			}
		}
	}

	/**
	 * Enable site example.com
	 *
	 * ## OPTIONS
	 *
	 * [<name>]
	 * : Name of the site to enable.
	 *
	 * ## EXAMPLES
	 *
	 *      # enable site.
	 *      $ ee site enable example.com
	 *
	 */
	public function enable( $args, $assoc_args ) {

		$site_name = empty( $args[0] ) ? '' : $args[0];

		while ( empty( $site_name ) ) {
			$value = EE::input_value( "Enter site name :" );
			if ( $value ) {
				$site_name = $value;
			}
		}

		$ee_domain = EE_Utils::validate_domain( $site_name );

		if ( ! is_site_exist( $ee_domain ) ) {
			EE::error( "site {$ee_domain} does not exist" );
		}

		if ( ee_file_exists( EE_NGINX_SITE_AVAIL_DIR . $ee_domain ) ) {
			EE::log( "Enable domain {$ee_domain}" );
			if ( ee_file_exists( EE_NGINX_SITE_ENABLE_DIR . $ee_domain ) ) {
				EE::debug( "Site {$ee_domain} already enabled." );
				EE::log( "[Failed]" );
			} else {
				ee_file_symlink( EE_NGINX_SITE_AVAIL_DIR . $ee_domain, EE_NGINX_SITE_ENABLE_DIR . $ee_domain );
				EE_Git::add( array( "/etc/nginx" ), "Enabled {$ee_domain} " );
				$data = array( 'is_enabled' => true );
				update_site( $data, array( 'site_name' => $ee_domain ) );
				EE::log( "[OK]" );
				if ( ! EE_Service::reload_service( 'nginx' ) ) {
					EE::error( "service nginx reload failed. check issues with `nginx -t` command" );
				}
			}
		} else {
			EE::error( "nginx configuration file does not exist" );
		}

	}

	/**
	 * Disable site example.com
	 *
	 * ## OPTIONS
	 *
	 * [<name>]
	 * : Name of the site to disable.
	 *
	 * ## EXAMPLES
	 *
	 *      # disable site.
	 *      $ ee site disable example.com
	 *
	 */
	public function disable( $args, $assoc_args ) {

		$site_name = empty( $args[0] ) ? '' : $args[0];

		while ( empty( $site_name ) ) {
			$value = EE::input_value( "Enter site name :" );
			if ( $value ) {
				$site_name = $value;
			}
		}

		$ee_domain = EE_Utils::validate_domain( $site_name );

		if ( ! is_site_exist( $ee_domain ) ) {
			EE::error( "site {$ee_domain} does not exist" );
		}

		if ( ee_file_exists( EE_NGINX_SITE_AVAIL_DIR . $ee_domain ) ) {
			EE::log( "Disable domain {$ee_domain}" );
			if ( ! ee_file_exists( EE_NGINX_SITE_ENABLE_DIR . $ee_domain ) ) {
				EE::debug( "Site {$ee_domain} already disabled" );
				EE::log( "[Failed]" );
			} else {
				ee_file_unlink( EE_NGINX_SITE_ENABLE_DIR . $ee_domain );
				EE_Git::add( array( "/etc/nginx" ), "Disabled {$ee_domain} " );
				$data = array( 'is_enabled' => false );
				update_site( $data, array( 'site_name' => $ee_domain ) );
				EE::log( "[OK]" );
				if ( ! EE_Service::reload_service( 'nginx' ) ) {
					EE::error( "service nginx reload failed. check issues with `nginx -t` command" );
				}
			}
		} else {
			EE::error( "nginx configuration file does not exist" );
		}
	}

	/**
	 * Update site.
	 *
	 * ## OPTIONS
	 *
	 * <name>
	 * : Name of the site to update.
	 *
	 * ## EXAMPLES
	 *
	 *      # update site.
	 *      $ ee site update example.com
	 *
	 */
	public function update( $args, $assoc_args ) {
		list( $site_name ) = $args;

		if ( ! empty( $site_name ) ) {
			EE::success( $site_name . ' site is updated successfully! ' );
		} else {
			EE::error( 'Please give site name . ' );
		}
	}

	/**
	 * Delete site.
	 *
	 * ## OPTIONS
	 *
	 * <name>
	 * : Name of the site to delete.
	 *
	 * ## EXAMPLES
	 *
	 *      # Delete site.
	 *      $ ee site delete example.com
	 *
	 */
	public function delete( $args, $assoc_args ) {

		list( $site_name ) = $args;

		if ( ! empty( $site_name ) ) {
			EE::success( $site_name . ' site is deleted successfully! ' );
		} else {
			EE::error( 'Please give site name . ' );
		}
	}

	/**
	 * Show site information.
	 *
	 * ## OPTIONS
	 *
	 * <name>
	 * : Name of the site to get site information.
	 *
	 * ## EXAMPLES
	 *
	 *      # Show site information.
	 *      $ ee site info example.com
	 */
	public function info( $args, $assoc_args ) {

		list( $site_name ) = $args;

		if ( empty( $site_name ) ) {
			/* @todo If site name not passed then ask for `Enter site name`. */
		}

		list( $ee_domain, $ee_www_domain ) = EE_Utils::validate_domain( $site_name );

		$ee_db_name = $ee_db_user = $ee_db_pass = $hhvm = '';

		if ( ! is_site_exist( $ee_domain ) ) {
			EE::error( 'Site ' . $ee_domain . ' does not exist.' );
		}


		if ( ee_file_exists( '/etc/nginx/sites-available/' . $ee_domain ) ) {

			$siteinfo        = site_info( $ee_domain );
			$siteinfo        = $siteinfo[0];
			$sitetype        = $siteinfo['site_type'];
			$cachetype       = $siteinfo['cache_type'];
			$ee_site_webroot = $siteinfo['site_path'];
			$access_log      = $ee_site_webroot . '/logs/access.log';
			$error_log       = $ee_site_webroot . '/logs/error.log';
			$ee_db_name      = $siteinfo['db_name'];
			$ee_db_user      = $siteinfo['db_user'];
			$ee_db_pass      = $siteinfo['db_password'];
			$ee_db_host      = $siteinfo['db_host'];
			$site_enabled    = ( $siteinfo['is_enabled'] ) ? 'enabled' : 'disabled';

			if ( 'html' === $sitetype ) {
				$hhvm = ( $siteinfo['is_hhvm'] ) ? 'enabled' : 'disabled';
			}

			if ( 'proxy' === $sitetype ) {
				$access_log      = '/var/log/nginx/' . $ee_domain . '.access.log';
				$error_log       = '/var/log/nginx/' . $ee_domain . '.error.log';
				$ee_site_webroot = '';
			}

			$php_version = $siteinfo['php_version'];
			// $pagespeed = ( $siteinfo['is_pagespeed'] ) ? 'enabled' : 'disabled';
			$ssl = ( $siteinfo['is_ssl'] ) ? 'enabled' : 'disabled';

			if ( 'enabled' === $ssl ) {
				$sslprovider = 'Lets Encrypt';
				$sslexpiry   = str( SSL . getExpirationDate( self, $ee_domain ) );
			} else {
				$sslprovider = $sslexpiry = '';
				$data        = array(
					'domain'      => $ee_domain,
					'webroot'     => $ee_site_webroot,
					'accesslog'   => $access_log,
					'errorlog'    => $error_log,
					'dbname'      => $ee_db_name,
					'dbuser'      => $ee_db_user,
					'php_version' => $php_version,
					'dbpass'      => $ee_db_pass,
					'hhvm'        => $hhvm,
					'ssl'         => 'ssl',
					'sslprovider' => $sslprovider,
					'sslexpiry'   => $sslexpiry,
					'type'        => $sitetype . ' ' . $cachetype . $site_enabled,
				);

				Utils\mustache_render( 'siteinfo.mustache', $data );
			}

		} else {
			EE::error( 'nginx configuration file does not exist for ' . $ee_domain );
		}
	}
}

EE::add_command( 'site', 'Site_Command' );