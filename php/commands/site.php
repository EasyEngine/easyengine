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
	 * ---
	 * default: html
	 * options:
	 *   - html
	 *   - php
	 *   - mysql
	 *   - wp
	 *   - wpsubdir
	 *   - wpsubdomain
	 *   - proxy
	 *
	 * [--cache=<cache>]
	 * : Cache for site.
	 * ---
	 * default: wpfc
	 * options:
	 *   - w3tc
	 *   - wpfc
	 *   - wpredis
	 *   - wpsc
	 *   - wpsubdir
	 *   - wpsubdomain
	 *   - proxy
	 *
	 * [--php]
	 * : Get PHP configuration information.
	 * ---
	 * default: 5.6
	 * options:
	 *   - 5.6
	 *   - 7.0
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
	 * : Configure letsencrypt ssl for the site
	 *
	 * [--experimental]
	 * : Enable Experimenal packages without prompt.
	 *
	 *
	 *
	 * ## EXAMPLES
	 *
	 *      # Create site.
	 *      $ ee site create example.com --type=wp --cache=wpredis
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
		$registered_stype  = array(
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
			'proxy',
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
			if ( in_array( $stype, $registered_stype ) ) {
				if ( 'proxy' == $stype ) {
					if(empty($assoc_args['ip'])){
						EE::error("--type=proxy option not set correctly.\nExample:\nee site create example.com --type=proxy --ip=127.0.0.1:22222".
												"\nee site create example.com --type=proxy --ip=127.0.0.1 --proxy=22222");
					}
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
				}elseif("wpredis"===$cache){
					$data['wpredis'] = true;
					$data['wp'] = true;
				}elseif("wpfc"===$cache){
					$data['wpfc'] = true;
					$data['wp'] = true;
				}elseif("wpsc"===$cache){
					$data['wpsc'] = true;
					$data['wp'] = true;
				}elseif("w3tc"===$cache){
					$data['w3tc'] = true;
					$data['wp'] = true;
				}

				if(isset($assoc_args['php']) && '7.0'=== $assoc_args['php']){
					$data['php_version'] = "7.0";
					$data['php7']      = true;
				}else{
					$data['php_version'] = "5.6";
				}
				$ee_auth = site_package_check( $stype, $cache );

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
						EE::info( 'Oops Something went wrong !!' );
						EE::info( 'Calling cleanup actions ...' );
						do_cleanup_action( $data );
						EE::debug( $e->getMessage() );
						EE::error( 'Check logs for reason `tail /var/log/ee/ee.log` & Try Again!!!' );
					}

					if ( ! empty( $data['php7'] ) && $data['php7'] ) {
						$data['php_version'] = "7.0";
					}

					// Add New Site record information into ee database.
					add_new_site( $data );

					if ( isset( $data['proxy'] ) && $data['proxy'] ) {
						$reload_nginx = EE_Service::reload_service( 'nginx' );
						if ( ! $reload_nginx ) {
							EE::info( 'Oops Something went wrong !!' );
							EE::info( 'Calling cleanup actions ...' );
							do_cleanup_action( $data );
							EE::error( 'Service nginx reload failed. check issues with `nginx -t` command.' );
							EE::error( 'Check logs for reason `tail /var/log/ee/ee.log` & Try Again!!!' );
						}
						if ( ! empty( $ee_auth ) ) {
							foreach ( $ee_auth as $msg ) {
								EE::info( $msg );
							}
						}
						EE::success( 'Successfully created site http://' . $ee_domain );
					}


					// Setup database for MySQL site.
					if ( isset( $data['ee_db_name'] ) && ! $data['wp'] ) {
						try {
							$data = setup_database( $data );
							update_site( $data, array( 'site_name' => $data['site_name'] ) );
						} catch ( Exception $e ) {
							EE::debug( $e->getMessage() );
							EE::info( "Oops Something went wrong !!" );
							EE::info( "Calling cleanup actions ..." );
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
							EE::info( "Oops Something went wrong !!" );
							EE::info( "Calling cleanup actions ..." );
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
							EE::warning( "Oops Something went wrong !!" );
							EE::warning( "Calling cleanup actions ..." );
							do_cleanup_action( $data );
							delete_site( array( 'site_name' => $ee_domain ) );
							EE::error( "Check logs for reason `tail /var/log/ee/ee.log` & Try Again!!!" );
						}
					}

					if ( ! EE_Service::reload_service( 'nginx' ) ) {
						EE::warning( "Oops Something went wrong !!" );
						EE::warning( "Calling cleanup actions ..." );
						do_cleanup_action( $data );
						delete_site( array( 'site_name' => $ee_domain ) );
						EE::warning( "service nginx reload failed. check issues with `nginx -t` command." );
						EE::error( "Check logs for reason `tail /var/log/ee/ee.log` & Try Again!!!" );
					}

					EE_Git::add( array( "/etc/nginx" ), "{$ee_www_domain} created with {$stype} {$cache}" );

					// Setup Permissions for webroot.
					try {
						set_webroot_permissions( $data['webroot'] );
					} catch ( Exception $e ) {
						EE::debug( $e->getMessage() );
						EE::info( "Oops Something went wrong !!" );
						EE::info( "Calling cleanup actions ..." );
						do_cleanup_action( $data );
						delete_site( array( 'site_name' => $ee_domain ) );
						EE::error( "Check logs for reason `tail /var/log/ee/ee.log` & Try Again!!!" );
					}

					if ( ! empty( $ee_auth ) ) {
						foreach ( $ee_auth as $msg ) {
							EE::info( $msg );
						}
					}

					if ( $data['wp'] ) {
						if ( ! empty( $ee_wp_creds ) ) {
							EE::info( "WordPress admin user : {$ee_wp_creds['wp_user']}" );
							EE::info( "WordPress admin user password : {$ee_wp_creds['wp_pass']}" );
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
						EE::info( "Letsencrypt is currently in beta phase. \nDo you wish to enable SSl now for {$ee_domain}?" );
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
						EE::info( "Creating Cron Job for cert auto-renewal" );
						EE_Cron::set_cron_weekly( 'ee site update --le=renew --all 2> /dev/null', 'Renew all letsencrypt SSL cert. Set by EasyEngine' );

						if ( EE_Service::reload_service( 'nginx' ) ) {
							EE::info( "service nginx reload failed. check issues with `nginx -t` command" );
						}

						EE::info( "Congratulations! Successfully Configured SSl for Site https://{$ee_domain}" );

						$ee_ssl_expiration_days = EE_Ssl::get_expiration_days( $ee_domain );

						if ( $ee_ssl_expiration_days > 0 ) {
							EE::info( "Your cert will expire within {$ee_ssl_expiration_days} days." );
						} else {
							EE::info( "Your cert already EXPIRED ! Please renew soon." );
						}

						EE_Git::add( array( "{$ee_site_webroot}/conf/nginx" ), "Adding letsencrypts config of site: {$ee_domain}" );
						update_site( $data, array( 'site_name' => $data['site_name'] ) );
					} else {
						EE::info( "Not using Let's encrypt for Site http://{$ee_domain}" );
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
			EE::info( "Enable domain {$ee_domain}" );
			if ( ee_file_exists( EE_NGINX_SITE_ENABLE_DIR . $ee_domain ) ) {
				EE::debug( "Site {$ee_domain} already enabled." );
				EE::info( "[Failed]" );
			} else {
				ee_file_symlink( EE_NGINX_SITE_AVAIL_DIR . $ee_domain, EE_NGINX_SITE_ENABLE_DIR . $ee_domain );
				EE_Git::add( array( "/etc/nginx" ), "Enabled {$ee_domain} " );
				$data = array( 'is_enabled' => true );
				update_site( $data, array( 'site_name' => $ee_domain ) );
				EE::info( "[OK]" );
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
			EE::info( "Disable domain {$ee_domain}" );
			if ( ! ee_file_exists( EE_NGINX_SITE_ENABLE_DIR . $ee_domain ) ) {
				EE::debug( "Site {$ee_domain} already disabled" );
				EE::info( "[Failed]" );
			} else {
				ee_file_unlink( EE_NGINX_SITE_ENABLE_DIR . $ee_domain );
				EE_Git::add( array( "/etc/nginx" ), "Disabled {$ee_domain} " );
				$data = array( 'is_enabled' => false );
				update_site( $data, array( 'site_name' => $ee_domain ) );
				EE::info( "[OK]" );
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
	 * [<name>]
	 * : Name of the site to create.
	 *
	 * [--type=<types>]
	 * : Type for create site.
	 * ---
	 * default: html
	 * options:
	 *   - html
	 *   - php
	 *   - mysql
	 *   - wp
	 *   - wpsubdir
	 *   - wpsubdomain
	 *   - proxy
	 *
	 * [--cache=<cache>]
	 * : Cache for site.
	 * ---
	 * default: wpfc
	 * options:
	 *   - w3tc
	 *   - wpfc
	 *   - wpredis
	 *   - wpsc
	 *   - wpsubdir
	 *   - wpsubdomain
	 *   - proxy
	 *
	 * [--php]
	 * : Get PHP configuration information.
	 * ---
	 * default: 5.6
	 * options:
	 *   - 5.6
	 *   - 7.0
	 *
	 * [--user=<username>]
	 * : Username for WordPress admin.
	 *
	 * [--email=<email>]
	 * : Email id for WordPress admin.
	 *
	 * [--password]
	 * : Password for WordPress admin.
	 *
	 * [--ip=<ip>]
	 * : Proxy ip address for proxy site.
	 *
	 * [--port=<port>]
	 * : Port no for porxy site.
	 *
	 * [--letsencrypt]
	 * : Configure letsencrypt ssl for the site
	 *
	 * [--experimental]
	 * : Enable Experimenal packages without prompt.
	 *
	 * ## EXAMPLES
	 *
	 *      # Update site.
	 *      $ ee site update example.com
	 *
	 */
	public function update( $args, $assoc_args ) {
		$site_name = empty( $args[0] ) ? '' : $args[0];

		if ( false || empty( $assoc_args['type'] ) || empty( $assoc_args['cache'] ) ) {
			//TODO : Filter assoc args.
			list( $stype, $cache ) = filter_site_assoc_args( $assoc_args );
		}

		if ( ! empty( $assoc_args['pagespeed'] ) ) {
			EE::error( "Pagespeed support has been dropped since EasyEngine v3.6.0", false );
			EE::error( "Please run command again without `--pagespeed`", false );
			EE::error( "For more details, read - https://easyengine.io/blog/disabling-pagespeed/" );
		}

		if ( ! empty( $assoc_args['type'] ) && 'hhvm' == $assoc_args['type'] ){
			EE::error( "Hhvm support has been dropped since EasyEngine v4.0", false );
			EE::error( "Please run command again with other type", false );
			// TODO :change hhvm link;
			EE::error( "For more details, read - " );
		}

		if ( ! empty( $assoc_args['all'] ) ) {
			if ( ! empty( $site_name ) ) {
				EE::error( "`--all` option cannot be used with site name provided" );
			}
			if ( empty( $assoc_args['type'] ) ) {
				EE::error( "Please provide --type to update sites." );
			}
			if ( 'html' === $assoc_args['type'] ) {
				EE::error( "No site can be updated to html" );
			}

			$sites = get_all_sites();

			if ( ! empty( $sites )) {
				foreach ( $sites as $site ) {
					EE::info( "Updating site {$site['sitename']}, please wait..." );
					do_update_site( $site['sitename'], $assoc_args );
				}
			}
		} else {
			while ( empty( $site_name ) ) {
				$value = EE::input_value( "Enter site name :" );
				if ( $value ) {
					$site_name = $value;
				}
			}
			do_update_site( $site_name, $assoc_args );
		}

	}

	/**
	 * Delete website configuration and files.
	 *
	 * ## OPTIONS
	 *
	 * [<name>]
	 * : Domain name to be deleted
	 *
	 * [--no-prompt]
	 * : Doesn't ask permission for delete.
	 *
	 * [--f]
	 * : Forcefully delete site and configuration.
	 *
	 * [--force]
	 * : Forcefully delete site and configuration.
	 *
	 * [--all]
	 * : Delete site database, webroot and nginx configuration.
	 *
	 * [--db]
	 * : Delete db only.
	 *
	 * [--files]
	 * : Delete webroot only.
	 *
	 * ## EXAMPLES
	 *
	 *      # Delete site.
	 *      $ ee site delete example.com
	 *
	 */
	public function delete( $args, $assoc_args ) {

		$site_name = empty( $args[0] ) ? '' : $args[0];

		while ( empty( $site_name ) ) {
			$value = EE::input_value( "Enter site name :" );
			if ( $value ) {
				$site_name = $value;
			}
		}

		$ee_domain     = EE_Utils::validate_domain( $site_name );

		if ( empty( $ee_domain ) ) {
			EE::error( 'Invalid domain name, Provide valid domain name' );
		}
		if ( ! is_site_exist( $ee_domain ) ) {
			EE::error( "Site {$ee_domain} does not exist" );
		}

		if ( empty( $assoc_args['db'] ) && empty( $assoc_args['files'] ) && empty( $assoc_args['all'] ) ) {
			$assoc_args['all'] = true;
		}

		$ee_db_name                 = '';
		$mark_db_delete_prompt      = false;
		$mark_webroot_delete_prompt = false;
		$mark_db_deleted            = false;
		$mark_webroot_deleted       = false;
		$check_site                 = get_site_info( $ee_domain );
		$ee_site_type = $check_site['site_type'];
		$ee_site_webroot = $check_site['site_path'];
		if ( 'deleted' === $ee_site_webroot ) {
			$mark_webroot_deleted = true;
		}

		if ( in_array( $ee_site_type, array( 'mysql', 'wp', 'wpsubdir', 'wpsubdomain' ) ) ) {
			$ee_db_name = $check_site['db_name'];
			$ee_db_user = $check_site['db_user'];
			$ee_mysql_grant_host = get_ee_config( 'mysql', 'grant-host' );
			if ( 'deleted' === $ee_db_name ) {
				$mark_db_deleted = true;
			}
			if ( ! empty( $assoc_args['all'] ) ) {
				$assoc_args['db']    = true;
				$assoc_args['files'] = true;
			}
		} else {
			if ( ! empty( $assoc_args['all'] ) ) {
				$mark_db_deleted     = true;
				$assoc_args['files'] = true;
			}
		}

		if ( isset( $assoc_args['db'] ) ) {
			if ( 'deleted' !== $ee_db_name and ! empty( $ee_db_name ) ) {
				if ( empty( $assoc_args['no_prompt'] ) ) {
					$ee_db_prompt = EE::input_value( "Are you sure, you want to delete database [y/N]: " );
				} else {
					$ee_db_prompt = 'Y';
					$mark_db_delete_prompt = true;
				}
				if ( 'y' === strtolower($ee_db_prompt)) {
					$mark_db_delete_prompt = true;
					EE::info( "Deleting Database, {$ee_db_name}, user {$ee_db_user}" );
					delete_db( $ee_db_name, $ee_db_user, $ee_mysql_grant_host, false );
					$data = array(
						'db_name'     => 'deleted',
						'db_user'     => 'deleted',
						'db_password' => 'deleted'
					);
					update_site( $data, array( 'site_name' => $ee_domain ) );
					$mark_db_deleted = true;
					EE::info( "Deleted Database successfully." );
				}
			} else {
				$mark_db_deleted = true;
				EE::info( "Does not seems to have database for this site." );
			}
		}

		if ( isset( $assoc_args['files'] ) ) {
			if ( 'deleted' !== $ee_site_webroot ) {
				if ( empty( $assoc_args['no_prompt'] ) ) {
					$ee_web_prompt = EE::input_value( "Are you sure, you want to delete webroot [y/N]: " );
				} else {
					$ee_web_prompt = 'Y';
					$mark_webroot_delete_prompt = true;
				}

				if ( 'y' === strtolower( $ee_web_prompt ) ) {
					$mark_webroot_delete_prompt = true;
					EE::info("Deleting Webroot, {$ee_site_webroot}");
					delete_web_root( $ee_site_webroot );
					$data = array(
						'webroot'     => 'deleted',
					);
					update_site( $data, array( 'site_name' => $ee_domain ) );
					$mark_webroot_deleted = true;
					EE::info( "Deleted webroot successfully" );
				}
			} else {
				$mark_webroot_deleted = true;
				EE::info( "Webroot seems to be already deleted" );
			}
		}

		if ( empty( $assoc_args['force'] ) ) {
			if ( $mark_webroot_deleted && $mark_db_deleted ) {
				remove_nginx_conf($ee_domain);
				delete_site( array( 'sitename' => $ee_domain ) );
				EE::info("Deleted site {$ee_domain}");
			}
		} else {
			if ( $mark_db_delete_prompt || $mark_webroot_delete_prompt || ( $mark_webroot_deleted && $mark_db_deleted ) ) {
				remove_nginx_conf($ee_domain);
				delete_site( array( 'sitename' => $ee_domain ) );
				EE::info("Deleted site {$ee_domain}");
			}
		}
	}

	/**
	 * Lists websites
	 *
	 * ## OPTIONS
	 *
	 * [--enabled]
	 * : List enabled websites.
	 *
	 * [--disabled]
	 * : List disabled websites.
	 *
	 * @subcommand list
	 *
	 * ## EXAMPLES
	 *
	 *	# Delete site.
	 *	$ ee site delete example.com
	 *
	 */
	public function _list( $args, $assoc_args ) {
		$where = array();
		if ( ! empty( $assoc_args['enabled'] ) ) {
			$where['is_enabled'] = true;
		} else if ( ! empty( $assoc_args['disabled'] ) ) {
			$where['is_enabled'] = false;
		}
		$sites = get_all_sites( $where );
		if ( ! empty( $sites ) ) {
			foreach ( $sites as $site ) {
				EE::info( $site['sitename'] );
			}
		} else {
			EE::error( "Site not available." );
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

		$site_name = empty( $args[0] ) ? '' : $args[0];

		while ( empty( $site_name ) ) {
			$value = EE::input_value( "Enter site name :" );
			if ( $value ) {
				$site_name = $value;
			}
		}

		$ee_domain = EE_Utils::validate_domain( $site_name );

		$ee_db_name = $ee_db_user = $ee_db_pass = $hhvm = '';

		if ( ! is_site_exist( $ee_domain ) ) {
			EE::error( 'Site ' . $ee_domain . ' does not exist.' );
		}


		if ( ee_file_exists( EE_NGINX_SITE_AVAIL_DIR . $ee_domain ) ) {

			$siteinfo        = get_site_info( $ee_domain );
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
				$sslexpiry   = EE_Ssl::get_expiration_date( $ee_domain );
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
					'hhvm'        => empty( $hhvm ) ? 'disabled' : $hhvm,
					'ssl'         => $ssl,
					'sslprovider' => $sslprovider,
					'sslexpiry'   => $sslexpiry,
					'type'        => $sitetype . ' ' . $cachetype . ' (' . $site_enabled . ')',
				);

				echo \EE\Utils\mustache_render( 'siteinfo.mustache', $data );
			}

		} else {
			EE::error( 'nginx configuration file does not exist for ' . $ee_domain );
		}
	}


	/**
	 * Monitor example.com logs
	 *
	 * ## OPTIONS
	 *
	 * <site-name>
	 * : Name of the site to monitor logs.
	 *
	 * ## EXAMPLES
	 *
	 *      # Monitor example.com logs
	 *      $ ee site log example.com
	 *
	 */
	public function log( $args, $assoc_args ) {

		$site_name       = empty( $args[0] ) ? '' : $args[0];
		$ee_domain       = EE_Utils::validate_domain( $site_name );
		$ee_site_info    = get_site_info( $site_name );
		$ee_site_webroot = $ee_site_info['site_path'];

		if ( ! is_site_exist( $ee_domain ) ) {
			EE::error( "site {$ee_domain} does not exist" );
		}
		$logfiles = $ee_site_webroot . '/logs/*.log';
		passthru( 'tail -f ' . $logfiles );
	}

	/**
	 * Display Nginx configuration of example.com
	 *
	 * ## OPTIONS
	 *
	 * <site-name>
	 * : Name of the site to display nginx configuration.
	 *
	 * ## EXAMPLES
	 *
	 *      # Display Nginx configuration of example.com
	 *      $ ee site show example.com
	 *
	 */
	public function show( $args, $assoc_args ) {

		$site_name       = empty( $args[0] ) ? '' : $args[0];
		$ee_domain       = EE_Utils::validate_domain( $site_name );
		$ee_site_info    = get_site_info( $site_name );
		$ee_site_webroot = $ee_site_info['site_path'];

		if ( ! is_site_exist( $ee_domain ) ) {
			EE::error( "site {$ee_domain} does not exist" );
		}

		if ( ee_file_exists( EE_NGINX_SITE_AVAIL_DIR . $ee_domain ) ) {
			EE::info( "Display NGINX configuration for {$ee_domain}" );
			$config_content = file_get_contents( EE_NGINX_SITE_AVAIL_DIR . $ee_domain );
			EE::info( $config_content );
		} else {
			EE::error( "nginx configuration file does not exists" );
		}
	}

	/**
	 * Change directory to site webroot of example.com
	 *
	 * ## OPTIONS
	 *
	 * <site-name>
	 * : Name of the site to change webroot dir.
	 *
	 * ## EXAMPLES
	 *
	 *      # Change directory to site webroot of example.com
	 *      $ ee site cd example.com
	 *
	 */
	public function cd( $args, $assoc_args ) {

		$site_name       = empty( $args[0] ) ? '' : $args[0];
		$ee_domain       = EE_Utils::validate_domain( $site_name );
		$ee_site_info    = get_site_info( $site_name );
		$ee_site_webroot = $ee_site_info['site_path'];

		if ( ! is_site_exist( $ee_domain ) ) {
			EE::error( "site {$ee_domain} does not exist" );
		}

		try {
			chdir( $ee_site_webroot );
			exec("bash > /dev/tty");
		} catch ( Exception $e ) {
			EE::debug( $e->getMessage() );
			EE::error( "unable to change directory" );
		}
	}

	/**
	 * Edit Nginx configuration of site.
	 *
	 * ## OPTIONS
	 *
	 * [<name>]
	 * : Name of the site to edit.
	 *
	 * [<--pagespeed>]
	 * : edit pagespeed configuration for site
	 *
	 * ## EXAMPLES
	 *
	 *      # disable site.
	 *      $ ee site disable example.com
	 *
	 */
	public function edit( $args, $assoc_args ) {

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

		if ( empty( $assoc_args["pagespeed"] ) ) {
			if ( ee_file_exists( EE_NGINX_SITE_AVAIL_DIR . $ee_domain ) ) {
				try {
					EE::invoke_editor( EE_NGINX_SITE_AVAIL_DIR . $ee_domain );
				} catch ( Exception $e ) {
					EE::debug( $e->getMessage() );
					EE::info( "Failed invoke editor" );
				}
				//Todo: Check EE_NGINX_SITE_AVAIL_DIR . $ee_domain  status if its change or not using git.
				EE_Git::add( array( "/etc/nginx" ), "Edit website: {$ee_domain}" );
				if ( ! EE_Service::reload_service( "nginx" ) ) {
					EE::error( "service nginx reload failed. check issues with `nginx -t` command" );
				}
			} else {
				EE::error( "nginx configuration file does not exist" );
			}
		} else {
			EE::error( "Pagespeed support has been dropped since EasyEngine v3.6.0", false );
			EE::error( "Please run command again without `--pagespeed`", false );
			EE::error( "For more details, read - https://easyengine.io/blog/disabling-pagespeed/" );
		}
	}


}

EE::add_command( 'site', 'Site_Command' );

