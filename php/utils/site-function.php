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