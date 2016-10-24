<?php

use Symfony\Component\Filesystem\Filesystem;

function pre_run_checks() {
	EE::log( 'Running pre-update checks, please wait...' );
	try {
		$check_nginx = EE::exec_cmd( 'nginx -t', 'checking NGINX configuration ...' );
		if ( 0 == $check_nginx ) {
			return true;
		}
	} catch ( \Exception $e ) {
		EE::debug( $e->getMessage() );
		EE::error( 'nginx configuration check failed.' );
	}

	return false;
}

function check_domain_exists( $domain ) {
	//Check in ee database.
	$site_exist = is_site_exist( $domain );

	return $site_exist;
}

function setup_domain( $data ) {
	$filesystem      = new Filesystem();
	$ee_domain_name  = $data['site_name'];
	$ee_site_webroot = ! empty( $data['webroot'] ) ? $data['webroot'] : '';
	EE::log( 'Setting up NGINX configuration' );
	try {
		$mustache_template = 'virtualconf-php7.mustache';
		if ( empty( $data['php7'] ) ) {
			$mustache_template = 'virtualconf.mustache';
		}
		EE::debug( 'Writting the nginx configuration to file /etc/nginx/conf.d/blockips.conf' );
		EE\Utils\mustache_write_in_file( EE_NGINX_SITE_AVAIL_DIR . $ee_domain_name, $mustache_template, $data );
	} catch ( \Exception $e ) {
		EE::error( 'create nginx configuration failed for site' );
	} finally {
		EE::debug( 'Checking generated nginx conf, please wait...' );
		self::pre_run_checks();
		$filesystem->symlink( EE_NGINX_SITE_AVAIL_DIR . $ee_domain_name, EE_NGINX_SITE_ENABLE_DIR . $ee_domain_name );
	}
	if ( empty( $data['proxy'] ) ) {
		EE::log( 'Setting up webroot' );
		try {
			$filesystem->symlink( '/var/log/nginx/' . $ee_domain_name . '.access.log', $ee_site_webroot . '/logs/access.log' );
			$filesystem->symlink( '/var/log/nginx/' . $ee_domain_name . '.error.log', $ee_site_webroot . '/logs/error.log' );
		} catch ( Exception $e ) {
			EE::debug( $e->getMessage() );
			EE::error( 'setup webroot failed for site' );
		} finally {
			if ( $filesystem->exists( $ee_site_webroot . '/htdocs' ) && $filesystem->exists( $ee_site_webroot . '/logs' ) ) {
				EE::log( 'Done' );
			} else {
				EE::log( 'Fail' );
				EE::error( 'setup webroot failed for site' );
			}
		}
	}
}

function setup_database( $data ) {
	$ee_domain_name      = $data['site_name'];
	$ee_db_domain_name   = str_replace( '.', '_', $ee_domain_name );
	$ee_random_password  = EE_Utils::generate_random( 15 );
	$prompt_dbname       = get_ee_config( 'mysql', 'db-name' );
	$prompt_dbuser       = get_ee_config( 'mysql', 'db-user' );
	$ee_mysql_grant_host = get_ee_config( 'mysql', 'grant-host' );
	$ee_db_name          = '';
	$ee_db_username      = '';
	$ee_db_password      = '';

	if ( 'true' === $prompt_dbname ) {
		try {
			$ee_db_name = EE::input_value( 'Enter the MySQL database name [' . $ee_db_domain_name . ']:' );
		} catch ( Exception $e ) {
			EE::debug( $e->getMessage() );
			EE::error( 'Unable to input database name' );
		}
	}

	if ( empty( $ee_db_name ) ) {
		$ee_db_name = $ee_db_domain_name;
	}

	if ( 'true' === $prompt_dbuser ) {
		try {
			$ee_db_username = EE::input_value( 'Enter the MySQL database user name [' . $ee_db_domain_name . ']:' );
			$ee_db_password = EE::input_hidden_value( 'Enter the MySQL database password [' . $ee_random_password . ']:' );
		} catch ( Exception $e ) {
			EE::debug( $e->getMessage() );
			EE::error( 'Unable to input database credentials' );
		}
	}
	if ( empty( $ee_db_username ) ) {
		$ee_db_username = $ee_db_domain_name;
	}

	if ( empty( $ee_db_password ) ) {
		$ee_db_password = $ee_random_password;
	}

	if ( strlen( $ee_db_username ) > 16 ) {
		EE::log( 'Autofix MySQL username (ERROR 1470 (HY000)), please wait' );
		$ee_db_username = substr( $ee_db_username, 0, 6 ) . EE_Utils::generate_random( 10, false );
	}

	EE::log( "Setting up database\t\t" );
	EE::debug( "Creating database {$ee_db_name}" );
	try {
		if ( EE_MySql::check_db_exists( $ee_db_name ) ) {
			EE::debug( "Database already exists, Updating DB_NAME .. " );
			$ee_db_name     = substr( $ee_db_name, 0, 6 ) . EE_Utils::generate_random( 10, false );
			$ee_db_username = $ee_db_name;
		}
		//		EE_MySql::execute( "CREATE DATABASE IF NOT EXISTS `{$ee_db_name}`" );
	} catch ( Exception $e ) {
		EE::debug( $e->getMessage() );
		EE::log( "MySQL Connectivity problem occured." );
	}
	try {
		EE_MySql::execute( "CREATE DATABASE IF NOT EXISTS `{$ee_db_name}`" );
	} catch ( Exception $e ) {
		EE::debug( $e->getMessage() );
		EE::log( "Create database execution failed" );
	}
	// Create MySQL User.
	EE::debug( "Creating user {$ee_db_username}" );
	EE::debug( "create user `{$ee_db_username}`@`{$ee_mysql_grant_host}` identified by ''" );
	try {
		EE_MySql::execute( "create user `{$ee_db_username}`@`{$ee_mysql_grant_host}` identified by {$ee_db_password}" );
	} catch ( Exception $e ) {
		EE::debug( $e->getMessage() );
		EE::log( "creating user failed for database" );
	}
	EE::debug( "Setting up user privileges" );

	try {
		EE_MySql::execute( "grant all privileges on `{$ee_db_name}`.* to `{$ee_db_username}`@`{$ee_mysql_grant_host}`" );
	} catch ( Exception $e ) {
		EE::debug( $e->getMessage() );
		EE::log( "grant privileges to user failed for database " );
	}
	EE::log( "[Done]" );

	$data['ee_db_name']          = $ee_db_name;
	$data['ee_db_user']          = $ee_db_username;
	$data['ee_db_pass']          = $ee_db_password;
	$data['ee_db_host']          = EE_Variables::get_ee_mysql_host();
	$data['ee_mysql_grant_host'] = $ee_mysql_grant_host;

	return $data;
}

function site_package_check( $stype ) {
	$apt_packages = array();
	$packages     = array();

	return $packages;
}

function delete_db( $dbname, $dbuser, $dbhost, $exit = true ) {

	try {
		try {
			if ( EE_MySql::check_db_exists( $dbname ) ) {
				EE::debug( "dropping database `{$dbname}`" );
				EE_MySql::execute( "drop database `{$dbname}`", "Unable to drop database {$dbname}" );
			}
		} catch ( Exception $e ) {
			EE::debug( $e->getMessage() );
			EE::log( "Database {$dbname} not dropped" );
		}

		if ( 'root' === $dbuser ) {
			EE::debug( "dropping user `{$dbuser}`" );
			try {
				EE_MySql::execute( "drop user `{$dbuser}`@`{$dbhost}`" );
			} catch ( Exception $e ) {
				EE::debug( "drop database user failed" );
				EE::debug( $e->getMessage() );
				EE::log( "Database {$dbname} not dropped" );
			}

			try {
				EE_MySql::execute( "flush privileges" );
			} catch ( Exception $e ) {
				EE::debug( "drop database failed" );
				EE::debug( $e->getMessage() );
				EE::log( "Database {$dbname} not dropped" );
			}
		}

	} catch ( Exception $e ) {
		EE::debug( $e->getMessage() );
		EE::log( "Error occured while deleting database" );
	}
}

function delete_web_root( $webroot ) {
	$invalid_webroot = array(
		"/var/www/",
		"/var/www",
		"/var/www/..",
		"/var/www/."
	);

	if ( in_array( $webroot, $invalid_webroot ) ) {
		EE::log( "Tried to remove {0}, but didn't remove it" );

		return false;
	}

	if ( is_dir( $webroot ) ) {
		EE::debug( "Removing {$webroot}" );
		// TODO: check if ee_file_remove will work or it needs array of files.
		ee_file_remove( $webroot );

		return true;
	} else {
		EE::debug( "{$webroot} does not exist" );

		return false;
	}
}

function remove_nginx_conf( $domain_name ) {
	if ( ee_file_exists( EE_NGINX_SITE_AVAIL_DIR . $domain_name ) ) {
		EE::debug( "Removing Nginx configuration" );
		ee_file_remove( EE_NGINX_SITE_ENABLE_DIR . $domain_name );
		ee_file_remove( EE_NGINX_SITE_AVAIL_DIR . $domain_name );
		EE_Service::reload_service( "nginx" );
		EE_Git::add( array( "/etc/nginx" ), "Deleted {$domain_name} " );
	}
}


function do_cleanup_action( $data ) {
	if ( ! empty( $data['site_name'] ) ) {
		if ( ee_file_exists( EE_NGINX_SITE_AVAIL_DIR . $data['site_name'] ) ) {
			remove_nginx_conf( $data['site_name'] );
		}
	}
	if ( ! empty( $data['webroot'] ) ) {
		delete_web_root( $data['webroot'] );
	}
	if ( ! empty( $data['ee_db_name'] ) ) {
		if ( ! empty( $data['ee_db_user'] ) ) {
			EE::log( "dbuser not provided" );

			return false;
		}
		if ( ! empty( $data['ee_db_host'] ) ) {
			EE::log( "dbhost not provided" );

			return false;
		}
		delete_db( $data['ee_db_name'], $data['ee_db_user'], $data['ee_db_host'] );
	}
}

function setup_wordpress( $data ) {
	$ee_domain_name  = $data['site_name'];
	$ee_site_webroot = $data['webroot'];
	$prompt_wpprefix = get_ee_config( 'wordpress', 'prefix' );
	$ee_wp_user      = get_ee_config( 'wordpress', 'user' );
	$ee_wp_pass      = get_ee_config( 'wordpress', 'password' );
	$ee_wp_email     = get_ee_config( 'wordpress', 'email' );
	$ee_wp_prefix    = '';
	$ee_random_pwd   = EE_Utils::generate_random( 15 );

	if ( ! empty( $data['wp-user'] ) ) {
		$ee_wp_user = $data['wp-user'];
	}

	if ( ! empty( $data['wp-email'] ) ) {
		$ee_wp_email = $data['wp-email'];
	}
	if ( ! empty( $data['wp-pass'] ) ) {
		$ee_wp_pass = $data['wp-pass'];
	}

	EE::log( "Downloading WordPress \t\t" );
	chdir( "{$ee_site_webroot}/htdocs/" );
	try {
		$wp_download = EE::exec_cmd( "wp --allow-root core download" );
		if ( 0 != $wp_download ) {
			EE::debug( "[Fail]" );
			EE::log( "download WordPress core failed" );

			return false;
		}
	} catch ( Exception $e ) {
		EE::debug( $e->getMessage() );
		EE::log( "download WordPress core failed" );
	}

	EE::log( "Done" );

	if ( ! empty( $data['ee_db_name'] ) && ! empty( $data['ee_db_user'] ) && ! empty( $data['ee_db_pass'] ) ) {
		$data = setup_database( $data );
	}

	if ( 'true' === strtolower( $prompt_wpprefix ) ) {
		try {
			$ee_wp_prefix = EE::input_value( 'Enter the WordPress table prefix [wp_]: ' );
			while ( empty( preg_match_all( '/^[A-Za-z0-9_]*$/i', $ee_wp_prefix ) ) ) {
				EE::log( "Table prefix can only contain numbers, letters, and underscores" );
				$ee_wp_prefix = EE::input_value( 'Enter the WordPress table prefix [wp_]: ' );
			}
		} catch ( Exception $e ) {
			EE::debug( $e->getMessage() );
			EE::log( "Input table prefix failed" );
		}
		if ( empty( $ee_wp_prefix ) ) {
			$ee_wp_prefix = 'wp_';
		}

		// Modify wp-config.php & move outside the webroot.
		chdir( $ee_site_webroot . '/htdocs/' );
		EE::debug( "Setting up wp-config file" );
		$ee_wp_cli_path = EE_Variables::get_ee_wp_cli_path();
		$wpredis        = empty( $data['wpredis'] ) ? '' : "\n\ndefine( WP_CACHE_KEY_SALT, {$ee_domain_name}: );";

		if ( false == $data['multisite'] ) {
			EE::debug( "Generating wp-config for WordPress Single site" );
			EE::debug( "bash -c \"php {$ee_wp_cli_path} --allow-root core config --dbname='{$data['ee_db_name']}' 
			 --dbprefix='{$ee_wp_prefix}' --dbuser='{$data['ee_db_user']}' --dbhost='{$data['ee_db_host']}' 
			 --dbpass={$data['ee_db_pass']} --extra-php<<PHP \n\ndefine(WP_DEBUG, false); {$wpredis} PHP\"" );

			try {

				$generate_config = EE::exec_cmd( "bash -c \"php {$ee_wp_cli_path} --allow-root core config --dbname='{$data['ee_db_name']}' 
			 --dbprefix='{$ee_wp_prefix}' --dbuser='{$data['ee_db_user']}' --dbhost='{$data['ee_db_host']}' 
			 --dbpass={$data['ee_db_pass']} --extra-php<<PHP \n\ndefine('WP_DEBUG', false); {$wpredis} PHP\"" );

				if ( 0 != $generate_config ) {
					EE::log( "Generate wp-config failed for wp single site" );
				}
			} catch ( Exception $e ) {
				EE::debug( $e->getMessage() );
				EE::log( "Generate wp-config failed for wp single site" );
			}
		} else {
			EE::log( "Generating wp-config for WordPress multisite" );
			EE::debug( "bash -c \"php {$ee_wp_cli_path} --allow-root core config --dbname='{$data['ee_db_name']}' 
			 --dbprefix='{$ee_wp_prefix}' --dbuser='{$data['ee_db_user']}' --dbhost='{$data['ee_db_host']}' 
			 --dbpass={$data['ee_db_pass']} --extra-php<<PHP \n\ndefine('WP_ALLOW_MULTISITE', true); \n\ndefine('WPMU_ACCEL_REDIRECT', true); 
			 \n\ndefine('WP_DEBUG', false); {$wpredis} PHP\"" );

			try {

				$generate_config = EE::exec_cmd( "bash -c \"php {$ee_wp_cli_path} --allow-root core config --dbname='{$data['ee_db_name']}' 
			 --dbprefix='{$ee_wp_prefix}' --dbuser='{$data['ee_db_user']}' --dbhost='{$data['ee_db_host']}' 
			 --dbpass={$data['ee_db_pass']} --extra-php<<PHP \n\ndefine('WP_ALLOW_MULTISITE', true); \n\ndefine('WPMU_ACCEL_REDIRECT', true); 
			 \n\ndefine('WP_DEBUG', false); {$wpredis} PHP\"" );

				if ( 0 != $generate_config ) {
					EE::log( "Generate wp-config failed for wp multi site" );
				}
			} catch ( Exception $e ) {
				EE::debug( $e->getMessage() );
				EE::log( "Generate wp-config failed for wp multi site" );
			}
		}

		$wp_config_htdocs_file = $ee_site_webroot . '/htdocs/wp-config.php';
		$wp_config_file        = $ee_site_webroot . '/wp-config.php';

		try {
			EE::debug( "Moving file from {$wp_config_htdocs_file} to {$wp_config_file}" );
			ee_file_rename( $wp_config_htdocs_file, $wp_config_file );
		} catch ( Exception $e ) {
			EE::debug( $e->getMessage() );
			EE::log( "Unable to move from {$wp_config_htdocs_file} to {$wp_config_file}\"" );

			return false;
		}

		if ( empty( $ee_wp_user ) ) {
			$ee_wp_user = get_ee_git_config( 'user', 'name' );
			while ( empty( $ee_wp_user ) ) {
				EE::warning( "Username can have only alphanumeric characters, spaces, underscores, hyphens, periods and the @ symbol." );
				try {
					$ee_wp_user = EE::input_value( 'Enter WordPress username: ' );
				} catch ( Exception $e ) {
					EE::debug( $e->getMessage() );
					EE::log( "input WordPress username failed" );
				}
			}
		}

		if ( empty( $ee_wp_pass ) ) {
			$ee_wp_pass = $ee_random_pwd;
		}

		if ( empty( $ee_wp_email ) ) {
			$ee_wp_email = get_ee_git_config( 'user', 'email' );
			while ( empty( $ee_wp_email ) ) {
				try {
					$ee_wp_email = EE::input_value( 'Enter WordPress email: ' );
				} catch ( Exception $e ) {
					EE::debug( $e->getMessage() );
					EE::log( "input WordPress username failed" );
				}
			}
		}

		try {
			while ( empty( preg_match_all( '/^[A-Za-z0-9\.\+_-]+@[A-Za-z0-9\._-]+\.[a-zA-Z]*$/i', $ee_wp_email ) ) ) {
				EE::log( "EMail not Valid in config, Please provide valid email id." );
				$ee_wp_email = EE::input_value( "Enter your email: " );
			}
		} catch ( Exception $e ) {
			EE::debug( $e->getMessage() );
			EE::log( "input WordPress user email failed" );
		}
		EE::debug( "Setting up WordPress tables" );

		if ( ! $data['multisite'] ) {
			EE::debug( "Creating tables for WordPress Single site" );
			EE::debug( "php {$ee_wp_cli_path} --allow-root core install 
				--url='{$data['www_domain']}' --title='{$data['www_domain']}'
				--admin_name={$ee_wp_user} --admin_password='{$ee_wp_pass}' --admin_email='{$ee_wp_email}'" );
			try {
				$wp_database = EE::exec_cmd( "php {$ee_wp_cli_path} --allow-root core install 
				--url='{$data['www_domain']}' --title='{$data['www_domain']}'
				--admin_name={$ee_wp_user} --admin_password='{$ee_wp_pass}' --admin_email='{$ee_wp_email}'" );
				if ( 0 !== $wp_database ) {
					EE::error( "setup WordPress tables failed for single site" );

					return false;
				}

			} catch ( Exception $e ) {
				EE::debug( $e->getMessage() );
				EE::error( "setup WordPress tables failed for single site" );

				return false;
			}

		} else {
			EE::debug( "Creating tables for WordPress multisite" );
			EE::debug( "php {$ee_wp_cli_path} --allow-root core multisite-install 
				--url='{$data['www_domain']}' --title='{$data['www_domain']}'
				--admin_name={$ee_wp_user} --admin_password='{$ee_wp_pass}' --admin_email='{$ee_wp_email}'" );
			try {
				$wp_database = EE::exec_cmd( "php {$ee_wp_cli_path} --allow-root core multisite-install 
				--url='{$data['www_domain']}' --title='{$data['www_domain']}'
				--admin_name={$ee_wp_user} --admin_password='{$ee_wp_pass}' --admin_email='{$ee_wp_email}'" );
				if ( 0 !== $wp_database ) {
					EE::error( "setup WordPress tables failed for wp multi site" );

					return false;
				}

			} catch ( Exception $e ) {
				EE::debug( $e->getMessage() );
				EE::error( "setup WordPress tables failed for wp multi site" );

				return false;
			}
		}

		EE::debug( "Updating WordPress permalink" );

		try {
			EE::exec_cmd( " php {$ee_wp_cli_path} --allow-root rewrite structure /%year%/%monthnum%/%day%/%postname%/" );
		} catch ( Exception $e ) {
			EE::debug( $e->getMessage() );
			EE::log( "Update wordpress permalinks failed" );
		}
	}
}

function install_wp_plugin( $plugin_name, $data ) {

}