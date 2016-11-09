<?php

use Symfony\Component\Filesystem\Filesystem;

function pre_run_checks() {
	EE::info( 'Running pre-update checks, please wait...' );
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

/**
 * TODO: Remove this function as it is duplicate of `is_site_exist( $domain )`  fun.
 * Check if site domain is exist in sqlite database or not.
 *
 * @param $domain
 *
 * @return bool
 */
function check_domain_exists( $domain ) {
	//Check in ee database.
	$site_exist = is_site_exist( $domain );

	return $site_exist;
}

/**
 * Setup domain webroot and config.
 *
 * @param $data
 */
function setup_domain( $data ) {
	$filesystem      = new Filesystem();
	$ee_domain_name  = $data['site_name'];
	$ee_site_webroot = ! empty( $data['webroot'] ) ? $data['webroot'] : '';
	EE::info( 'Setting up NGINX configuration' );
	try {
		$mustache_template = 'virtualconf-php7.mustache';
		if ( empty( $data['php7'] ) ) {
			$mustache_template = 'virtualconf.mustache';
		}
		EE::info( 'Writting the nginx configuration to file '.EE_NGINX_SITE_AVAIL_DIR . $ee_domain_name );
		EE\Utils\mustache_write_in_file( EE_NGINX_SITE_AVAIL_DIR . $ee_domain_name, $mustache_template, $data );
	} catch ( \Exception $e ) {
		EE::error( 'create nginx configuration failed for site' );
	} finally {
		try {
			EE::info( 'Checking generated nginx conf, please wait...' );
			EE::exec_cmd( "nginx -t", '', false, false );
			EE::debug( "[Done]" );
		} catch ( Exception $e ) {
			EE::debug( "[Fail]" );
			EE::error( "created nginx configuration failed for site. check with `nginx -t`" );
		}
	}

	$filesystem->symlink( EE_NGINX_SITE_AVAIL_DIR . $ee_domain_name, EE_NGINX_SITE_ENABLE_DIR . $ee_domain_name );

	if ( empty( $data['proxy'] ) ) {
		EE::info( 'Setting up webroot' );
		try {
			if ( ! ee_file_exists( "{$ee_site_webroot}/htdocs" ) ) {
				ee_file_mkdir( "{$ee_site_webroot}/htdocs" );
			}
			if ( ! ee_file_exists( "{$ee_site_webroot}/logs" ) ) {
				ee_file_mkdir( "{$ee_site_webroot}/logs" );
			}
			if ( ! ee_file_exists( "{$ee_site_webroot}/conf/nginx" ) ) {
				ee_file_mkdir( "{$ee_site_webroot}/conf/nginx" );
			}
			$filesystem->symlink( '/var/log/nginx/' . $ee_domain_name . '.access.log', $ee_site_webroot . '/logs/access.log' );
			$filesystem->symlink( '/var/log/nginx/' . $ee_domain_name . '.error.log', $ee_site_webroot . '/logs/error.log' );
		} catch ( Exception $e ) {
			EE::debug( $e->getMessage() );
			EE::error( 'setup webroot failed for site' );
		} finally {
			if ( $filesystem->exists( $ee_site_webroot . '/htdocs' ) && $filesystem->exists( $ee_site_webroot . '/logs' ) ) {
				EE::debug( 'Done' );
			} else {
				EE::debug( 'Fail' );
				EE::error( 'setup webroot failed for site' );
			}
		}
	}
}

/**
 * Setup database for created site.
 *
 * @param $data
 *
 * @return mixed
 */
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
		EE::debug( 'Autofix MySQL username (ERROR 1470 (HY000)), please wait' );
		$ee_db_username = substr( $ee_db_username, 0, 6 ) . EE_Utils::generate_random( 10, false );
	}

	EE::info( "Setting up database\t\t" );
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
		EE::info( "MySQL Connectivity problem occured." );
	}
	try {
		EE_MySql::execute( "CREATE DATABASE IF NOT EXISTS `{$ee_db_name}`" );
	} catch ( Exception $e ) {
		EE::debug( $e->getMessage() );
		EE::info( "Create database execution failed" );
	}
	// Create MySQL User.
	EE::debug( "Creating user {$ee_db_username}" );
	EE::debug( "create user `{$ee_db_username}`@`{$ee_mysql_grant_host}` identified by ''" );
	try {
		EE_MySql::execute( "create user `{$ee_db_username}`@`{$ee_mysql_grant_host}` identified by '{$ee_db_password}'" );
	} catch ( Exception $e ) {
		EE::debug( $e->getMessage() );
		EE::info( "creating user failed for database" );
	}
	EE::debug( "Setting up user privileges" );

	try {
		EE_MySql::execute( "grant all privileges on `{$ee_db_name}`.* to `{$ee_db_username}`@`{$ee_mysql_grant_host}`" );
	} catch ( Exception $e ) {
		EE::debug( $e->getMessage() );
		EE::info( "grant privileges to user failed for database " );
	}
	EE::info( "[Done]" );

	$data['ee_db_name']          = $ee_db_name;
	$data['ee_db_user']          = $ee_db_username;
	$data['ee_db_pass']          = $ee_db_password;
	$data['ee_db_host']          = EE_Variables::get_ee_mysql_host();
	$data['ee_mysql_grant_host'] = $ee_mysql_grant_host;

	return $data;
}

/**
 * Check packages related site type are installed or not.
 *
 * @param $stype
 *
 * @return mixed
 */
function site_package_check( $stype, $cache='' ) {
	$stack_required = array();
	if ( in_array( $stype, array( 'html', 'proxy', 'php', 'mysql', 'wp', 'wpsubdir', 'wpsubdomain', 'php7' ) ) ) {

		// Check if server has nginx-custom package.
		if ( ! EE_Apt_Get::is_installed( 'nginx-custom' ) ) {
			// Check if Server has nginx-plus installed.
			if ( EE_Apt_Get::is_installed( 'nginx-plus' ) ) {
				EE::info( "NGINX PLUS Detected ..." );
				$packages = array();
				$apt   = EE_Variables::get_nginx_packages();
				$apt[] = "nginx-plus";
				Stack_Command::post_pref($apt, $packages );
			} else if ( EE_Apt_Get::is_installed( 'nginx' ) ) {
				$packages = array();
				EE::info( "EasyEngine detected a previously installed Nginx package. " .
				         "It may or may not have required modules. " .
				         "\nIf you need help, please create an issue at https://github.com/EasyEngine/easyengine/issues/ \n" );
				$apt   = EE_Variables::get_nginx_packages();
				$apt[] = "nginx";
				Stack_Command::post_pref($apt, $packages );
			} else {
				$stack_required['nginx']=true;
			}
		} else {
			if ( ! grep_string( '/etc/nginx/fastcgi_params', 'SCRIPT_FILENAME' ) ) {
				ee_file_append_content( '/etc/nginx/fastcgi_params', 'fastcgi_param \tSCRIPT_FILENAME \t$request_filename;\n' );
			}
		}
	}

	if ( 'php7' == $stype ) {
		EE::error( "INVALID OPTION: PHP 7.0 provided with PHP 5.0" );
	}
	$ee_platform_codename = EE_OS::ee_platform_codename();
	if ( in_array( $stype, array( 'php', 'mysql', 'wp', 'wpsubdir', 'wpsubdomain' ) ) ) {

		if(!grep_string(EE_Variables::get_php_path()."/5.6/fpm/php.ini","EasyEngine") || !grep_string(EE_Variables::get_php_path()."/7.0/fpm/php.ini","EasyEngine")){
				EE::info( "Setting apt_packages variable for PHP" );
				$stack_required['php']=true;
			}

		if ( ee_file_exists( "/etc/nginx/conf.d/upstream.conf" ) ) {
			if ( ! grep_string( "/etc/nginx/conf.d/upstream.conf", "php7" ) ) {
				$upstream_config_content = "upstream php7 {\nserver 127.0.0.1:9070;\n}\n" .
				                           "upstream debug7 {\nserver 127.0.0.1:9170;\n}\n";
				ee_file_append_content( "/etc/nginx/conf.d/upstream.conf", $upstream_config_content );
			}
		}

	}

	// TODO : check if --php7 will be pass in the command.
	//	if ( 'php7' == $stype && in_array( $stype, array( 'mysql', 'wp', 'wpsubdir', 'wpsubdomain' ) ) ) {
	//
	//	}

	if ( in_array( $stype, array( 'mysql', 'wp', 'wpsubdir', 'wpsubdomain' ) ) ) {
		EE::debug( "Setting apt_packages variable for MySQL" );
		if ( 0 !== EE::exec_cmd( "mysqladmin ping" ) ) {
			$stack_required['mysql']=true;

		}
	}

	if ( in_array( $stype, array( 'php', 'mysql', 'wp', 'wpsubdir', 'wpsubdomain' ) ) ) {
		EE::debug( "Setting apt_packages variable for Postfix" );
		if ( ! EE_Apt_Get::is_installed( 'postfix' ) ) {
			$stack_required['postfix']=true;
		}
	}

	if ( in_array( $stype, array( 'wp', 'wpsubdir', 'wpsubdomain' ) ) ) {
		EE::debug( "Setting packages variable for WP-CLI" );
		if ( 0 !== EE::exec_cmd( "which wp" ) ) {
			$stack_required['wpcli']=true;
		}
	}

	if ( 'wpredis' === $cache ) {
		EE::debug( "Setting apt_packages variable for redis" );
		if ( ! EE_Apt_Get::is_installed( 'redis-server' ) ) {
			$stack_required['redis']=true;
		}
	}




	$stack_required['no_diplay_message']=true;

	$install_packages = EE_Stack::install($stack_required );

	return $install_packages;
}

/**
 * Delete site database when deleting the site.
 *
 * @param      $dbname
 * @param      $dbuser
 * @param      $dbhost
 * @param bool $exit
 */
function delete_db( $dbname, $dbuser, $dbhost, $exit = true ) {

	try {
		try {
			if ( EE_MySql::check_db_exists( $dbname ) ) {
				EE::debug( "dropping database `{$dbname}`" );
				EE_MySql::execute( "drop database `{$dbname}`", "Unable to drop database {$dbname}" );
			}
		} catch ( Exception $e ) {
			EE::debug( $e->getMessage() );
			EE::info( "Database {$dbname} not dropped" );
		}

		if ( 'root' === $dbuser ) {
			EE::debug( "dropping user `{$dbuser}`" );
			try {
				EE_MySql::execute( "drop user `{$dbuser}`@`{$dbhost}`" );
			} catch ( Exception $e ) {
				EE::debug( "drop database user failed" );
				EE::debug( $e->getMessage() );
				EE::info( "Database {$dbname} not dropped" );
			}

			try {
				EE_MySql::execute( "flush privileges" );
			} catch ( Exception $e ) {
				EE::debug( "drop database failed" );
				EE::debug( $e->getMessage() );
				EE::info( "Database {$dbname} not dropped" );
			}
		}

	} catch ( Exception $e ) {
		EE::debug( $e->getMessage() );
		EE::info( "Error occured while deleting database" );
	}
}

/**
 * Remove webroot of site.
 *
 * @param $webroot
 *
 * @return bool
 */
function delete_web_root( $webroot ) {
	$invalid_webroot = array(
		"/var/www/",
		"/var/www",
		"/var/www/..",
		"/var/www/."
	);

	if ( in_array( $webroot, $invalid_webroot ) ) {
		EE::info( "Tried to remove {0}, but didn't remove it" );

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

/**
 * Remove nginx config of site.
 *
 * @param $domain_name
 */
function remove_nginx_conf( $domain_name ) {
	if ( ee_file_exists( EE_NGINX_SITE_AVAIL_DIR . $domain_name ) ) {
		EE::debug( "Removing Nginx configuration" );
		ee_file_remove( EE_NGINX_SITE_ENABLE_DIR . $domain_name );
		ee_file_remove( EE_NGINX_SITE_AVAIL_DIR . $domain_name );
		EE_Service::reload_service( "nginx" );
		EE_Git::add( array( "/etc/nginx" ), "Deleted {$domain_name} " );
	}
}

/**
 * Cleanup data of site. i.e webroot, database etc.
 *
 * @param $data
 *
 * @return bool
 */
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
			EE::info( "dbuser not provided" );

			return false;
		}
		if ( ! empty( $data['ee_db_host'] ) ) {
			EE::info( "dbhost not provided" );

			return false;
		}
		delete_db( $data['ee_db_name'], $data['ee_db_user'], $data['ee_db_host'] );
	}
}

/**
 * Setup WordPress site.
 *
 * @param $data
 *
 * @return array|boolean
 */
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

	EE::info( "Downloading WordPress \t\t" );
	chdir( "{$ee_site_webroot}/htdocs/" );
	try {
		$wp_download = EE::exec_cmd( "wp --allow-root core download" );
		if ( 0 != $wp_download ) {
			EE::debug( "[Fail]" );
			EE::info( "download WordPress core failed" );

			return false;
		}
	} catch ( Exception $e ) {
		EE::debug( $e->getMessage() );
		EE::info( "download WordPress core failed" );
	}

	EE::debug( "Done" );

	if ( empty( $data['ee_db_name'] ) && empty( $data['ee_db_user'] ) && empty( $data['ee_db_pass'] ) ) {
		$data        = setup_database( $data );
		$update_data = update_site( $data, array( 'site_name' => $data['site_name'] ) );
	}

	if ( 'true' === strtolower( $prompt_wpprefix ) ) {
		try {
			$ee_wp_prefix = EE::input_value( 'Enter the WordPress table prefix [wp_]: ' );
			while ( empty( preg_match_all( '/^[A-Za-z0-9_]*$/i', $ee_wp_prefix ) ) ) {
				EE::info( "Table prefix can only contain numbers, letters, and underscores" );
				$ee_wp_prefix = EE::input_value( 'Enter the WordPress table prefix [wp_]: ' );
			}
		} catch ( Exception $e ) {
			EE::debug( $e->getMessage() );
			EE::info( "Input table prefix failed" );
		}
	}

	if ( empty( $ee_wp_prefix ) ) {
		$ee_wp_prefix = 'wp_';
	}

	// Modify wp-config.php & move outside the webroot.
	chdir( $ee_site_webroot . '/htdocs/' );
	EE::debug( "Setting up wp-config file" );
	$ee_wp_cli_path = EE_Variables::get_ee_wp_cli_path();
	$wpredis        = empty( $data['wpredis'] ) ? '' : "\n\ndefine('WP_CACHE_KEY_SALT', '{$ee_domain_name}:');";

	if ( false == $data['multisite'] ) {
		EE::debug( "Generating wp-config for WordPress Single site" );
		$generate_config_cmd = "bash -c \"php {$ee_wp_cli_path} --allow-root core config --dbname='{$data['ee_db_name']}'";
		$generate_config_cmd .= " --dbprefix='{$ee_wp_prefix}' --dbuser='{$data['ee_db_user']}' --dbhost='{$data['ee_db_host']}'";
		$generate_config_cmd .= " --dbpass='{$data['ee_db_pass']}' --extra-php <<PHP\ndefine('WP_DEBUG', false); {$wpredis} \nPHP\"";
		EE::debug( $generate_config_cmd );

		try {

			$generate_config = EE::exec_cmd( $generate_config_cmd );

			if ( 0 != $generate_config ) {
				EE::info( "Generate wp-config failed for wp single site" );
			}
		} catch ( Exception $e ) {
			EE::debug( $e->getMessage() );
			EE::info( "Generate wp-config failed for wp single site" );
		}
	} else {
		EE::info( "Generating wp-config for WordPress multisite" );
		$generate_config_cmd = "bash -c \"php {$ee_wp_cli_path} --allow-root core config --dbname='{$data['ee_db_name']}'";
		$generate_config_cmd .= " --dbprefix='{$ee_wp_prefix}' --dbuser='{$data['ee_db_user']}' --dbhost='{$data['ee_db_host']}'";
		$generate_config_cmd .= " --dbpass='{$data['ee_db_pass']}' --extra-php <<PHP\n\ndefine('WP_ALLOW_MULTISITE', true);";
		$generate_config_cmd .= "\n\ndefine('WPMU_ACCEL_REDIRECT', true);\n\ndefine('WP_DEBUG', false); {$wpredis} \nPHP\"";


		EE::debug( $generate_config_cmd );

		try {

			$generate_config = EE::exec_cmd( $generate_config_cmd );

			if ( 0 != $generate_config ) {
				EE::info( "Generate wp-config failed for wp multi site" );
			}
		} catch ( Exception $e ) {
			EE::debug( $e->getMessage() );
			EE::info( "Generate wp-config failed for wp multi site" );
		}
	}

	$wp_config_htdocs_file = $ee_site_webroot . '/htdocs/wp-config.php';
	$wp_config_file        = $ee_site_webroot . '/wp-config.php';

	try {
		EE::debug( "Moving file from {$wp_config_htdocs_file} to {$wp_config_file}" );
		ee_file_rename( $wp_config_htdocs_file, $wp_config_file );
	} catch ( Exception $e ) {
		EE::debug( $e->getMessage() );
		EE::info( "Unable to move from {$wp_config_htdocs_file} to {$wp_config_file}\"" );

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
				EE::info( "input WordPress username failed" );
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
				EE::info( "input WordPress username failed" );
			}
		}
	}

	try {
		while ( empty( preg_match_all( '/^[A-Za-z0-9\.\+_-]+@[A-Za-z0-9\._-]+\.[a-zA-Z]*$/i', $ee_wp_email ) ) ) {
			EE::info( "Email not Valid in config, Please provide valid email id." );
			$ee_wp_email = EE::input_value( "Enter your email: " );
		}
	} catch ( Exception $e ) {
		EE::debug( $e->getMessage() );
		EE::info( "input WordPress user email failed" );
	}
	EE::debug( "Setting up WordPress tables" );

	if ( ! $data['multisite'] ) {
		EE::debug( "Creating tables for WordPress Single site" );
		$wp_database_cmd = "php {$ee_wp_cli_path} --allow-root core install";
		$wp_database_cmd .= " --url='{$data['www_domain']}' --title='{$data['www_domain']}'";
		$wp_database_cmd .= " --admin_name='{$ee_wp_user}' --admin_password='{$ee_wp_pass}' --admin_email='{$ee_wp_email}'";

		EE::debug( $wp_database_cmd );
		try {
			$wp_database = EE::exec_cmd( $wp_database_cmd );
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
		$wp_database_cmd = "php {$ee_wp_cli_path} --allow-root core multisite-install";
		$wp_database_cmd .= " --url='{$data['www_domain']}' --title='{$data['www_domain']}'";
		$wp_database_cmd .= " --admin_name='{$ee_wp_user}' --admin_password='{$ee_wp_pass}' --admin_email='{$ee_wp_email}'";


		EE::debug( $wp_database_cmd );
		try {
			$wp_database = EE::exec_cmd( $wp_database_cmd );
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
		EE::info( "Update wordpress permalinks failed" );
	}

	install_wp_plugin( 'nginx-helper', $data );
	// Install Wp Super Cache.
	if ( $data['wpsc'] ) {
		install_wp_plugin( 'wp-super-cache', $data );
	}

	// Install Redis Cache.
	if ( $data['wpredis'] ) {
		install_wp_plugin( 'redis-cache', $data );
		//Activate Object Caching
		ee_file_copy($ee_site_webroot."/htdocs/wp-content/plugins/redis-cache/includes/object-cache.php",$ee_site_webroot."/htdocs/wp-content/object-cache.php");
	}

	// Install W3 Total Cache.
	if ( $data['w3tc'] || $data['wpfc'] ) {
		install_wp_plugin( 'w3-total-cache', $data );
	}
	#setup Nginx Helper plugin
	if ( $data['wpfc'] ) {
		try {
			$plugin_data = '{"log_level":"INFO","log_filesize":5,"enable_purge":1,"enable_map":0,"enable_log":0,';
			$plugin_data .= '"enable_stamp":0,"purge_homepage_on_new":1,"purge_homepage_on_edit":1,"purge_homepage_on_del":1,';
			$plugin_data .= '"purge_archive_on_new":1,"purge_archive_on_edit":0,"purge_archive_on_del":0,"purge_archive_on_new_comment":0,';
			$plugin_data .= '"purge_archive_on_deleted_comment":0,"purge_page_on_mod":1,"purge_page_on_new_comment":1,';
			$plugin_data .= '"purge_page_on_deleted_comment":1,"cache_method":"enable_fastcgi","purge_method":"get_request",';
			$plugin_data .= '"redis_hostname":"127.0.0.1","redis_port":"6379","redis_prefix":"nginx-cache:"}';
			setup_wp_plugin( 'nginx-helper', 'rt_wp_nginx_helper_options', $plugin_data, $data );
		} catch ( Exception $e ) {
			EE::debug( $e->getMessage() );
			EE::info( "Update site failed. Check logs for reason `tail /var/log/ee/ee.log` & Try Again!!!" );
			return 1;
		}
	} else if ( $data['wpredis'] )  {
		try {
			$plugin_data = '{"log_level":"INFO","log_filesize":5,"enable_purge":1,"enable_map":0,"enable_log":0,';
			$plugin_data .= '"enable_stamp":0,"purge_homepage_on_new":1,"purge_homepage_on_edit":1,"purge_homepage_on_del":1,';
			$plugin_data .= '"purge_archive_on_new":1,"purge_archive_on_edit":0,"purge_archive_on_del":0,"purge_archive_on_new_comment":0,';
			$plugin_data .= '"purge_archive_on_deleted_comment":0,"purge_page_on_mod":1,"purge_page_on_new_comment":1,';
			$plugin_data .= '"purge_page_on_deleted_comment":1,"cache_method":"enable_redis","purge_method":"get_request",';
			$plugin_data .= '"redis_hostname":"127.0.0.1","redis_port":"6379","redis_prefix":"nginx-cache:"}';
			setup_wp_plugin( 'nginx-helper', 'rt_wp_nginx_helper_options', $plugin_data, $data );
		} catch ( Exception $e ) {
			EE::debug( $e->getMessage() );
			EE::info( "Update site failed. Check logs for reason `tail /var/log/ee/ee.log` & Try Again!!!" );
			return 1;
		}
	} else {
		try {
			$plugin_data = '{"log_level":"INFO","log_filesize":5,"enable_purge":0,"enable_map":0,';
			$plugin_data .= '"enable_log":0,"enable_stamp":0,"purge_homepage_on_new":1,"purge_homepage_on_edit":1,';
			$plugin_data .= '"purge_homepage_on_del":1,"purge_archive_on_new":1,"purge_archive_on_edit":0,"purge_archive_on_del":0,';
			$plugin_data .= '"purge_archive_on_new_comment":0,"purge_archive_on_deleted_comment":0,"purge_page_on_mod":1,';
			$plugin_data .= '"purge_page_on_new_comment":1,"purge_page_on_deleted_comment":1,"cache_method":"enable_redis",';
			$plugin_data .= '"purge_method":"get_request","redis_hostname":"127.0.0.1","redis_port":"6379","redis_prefix":"nginx-cache:"}';
			setup_wp_plugin( 'nginx-helper', 'rt_wp_nginx_helper_options', $plugin_data, $data );
		} catch ( Exception $e ) {
			EE::debug( $e->getMessage() );
			EE::info( "Update site failed. Check logs for reason `tail /var/log/ee/ee.log` & Try Again!!!" );
			return 1;
		}
	}

	$wp_creds = array(
		'wp_user'  => $ee_wp_user,
		'wp_pass'  => $ee_wp_pass,
		'wp_email' => $ee_wp_email
	);

	return $wp_creds;

}

function setup_wordpress_network( $data ) {
	$ee_site_webroot = $data['webroot'];
	chdir( "{$ee_site_webroot}/htdocs/" );
	EE::info( "Setting up WordPress Network \t" );
	try {
		$subdomains = empty( $data['wpsubdir'] ) ? '--subdomains' : '';
		if ( 0 !== EE::exec_cmd( "wp --allow-root core multisite-convert --title='{$data['www_domain']}' {$subdomains}" ) ) {
			EE::error( "setup WordPress network failed" );
		}
		
	} catch ( Exception $e ) {
		EE::debug( $e->getMessage() );
		EE::info( "setup WordPress network failed" );
	}
	EE::info( "[Done]" );
}

function setup_wp_plugin( $plugin_name, $plugin_option, $plugin_data, $data ) {
	$ee_site_webroot = $data['webroot'];
	EE::info( "Setting plugin {$plugin_name}, please wait..." );
	chdir( "{$ee_site_webroot}/htdocs/" );
	$ee_wpcli_path = EE_Variables::get_ee_wp_cli_path();
	if ( ! $data['multisite'] ) {
		try {
			EE::exec_cmd("php {$ee_wpcli_path} --allow-root option update {$plugin_option} '{$plugin_data}' --format=json");
		} catch ( Exception $e ) {
			EE::debug( $e->getMessage() );
			EE::info( "plugin setup failed" );
		}
	} else {
		try {
			EE::exec_cmd("php {$ee_wpcli_path} --allow-root network meta update 1 {$plugin_option} '{$plugin_data}' --format=json");
		} catch ( Exception $e ) {
			EE::debug( $e->getMessage() );
			EE::info( "plugin setup failed" );
		}
	}
}

/**
 * Install WordPress plugin in WP site.
 *
 * @param $plugin_name
 * @param $data
 */
function install_wp_plugin( $plugin_name, $data ) {
	$ee_site_webroot = $data['webroot'];
	EE::info( "Installing plugin {$plugin_name}, please wait..." );
	chdir( "{$ee_site_webroot}/htdocs/" );
	$ee_wpcli_path = EE_Variables::get_ee_wp_cli_path();
	try {
		EE::exec_cmd( "php {$ee_wpcli_path} plugin --allow-root install {$plugin_name}" );
	} catch ( Exception $e ) {
		EE::debug( $e->getMessage() );
		EE::info( "plugin installation failed" );
	}
	try {
		$network = $data['multisite'] ? '--network' : '';
		EE::exec_cmd( "php {$ee_wpcli_path} plugin --allow-root activate {$plugin_name} {$network}" );
	} catch ( Exception $e ) {
		EE::debug( $e->getMessage() );
		EE::info( "plugin activation failed" );
	}

	return true;
}

/**
 * Uninstall plugin in WordPress site.
 *
 * @param $plugin_name
 * @param $data
 */
function uninstall_wp_plugin( $plugin_name, $data ) {
	$ee_site_webroot = $data['webroot'];
	EE::info( "Uninstalling plugin {$plugin_name}, please wait..." );
	chdir( "{$ee_site_webroot}/htdocs/" );
	EE::info( "Uninstalling plugin {$plugin_name}, please wait..." );
	$ee_wpcli_path = EE_Variables::get_ee_wp_cli_path();
	try {
		EE::exec_cmd( "php {$ee_wpcli_path} plugin --allow-root deactivate {$plugin_name}" );
		EE::exec_cmd( "php {$ee_wpcli_path} plugin --allow-root uninstall {$plugin_name}" );
	} catch ( Exception $e ) {
		EE::debug( $e->getMessage() );
		EE::info( "plugin uninstall failed" );
	}
}


/**
 * Set permission to webroot for php(www-data) user.
 *
 * @param $webroot
 */
function set_webroot_permissions( $webroot ) {
	EE::info( "Setting up permissions" );
	$ee_php_user = EE_Variables::get_ee_php_user();
	try {
		ee_file_chown( $webroot, $ee_php_user, true );
	} catch ( Exception $e ) {
		EE::debug( $e->getMessage() );
		EE::info( "problem occured while setting up webroot permissions" );
	}
}

/**
 * Display cache settings.
 *
 * @param $data
 */
function display_cache_settings( $data ) {
	if ( $data['wpsc'] ) {
		if ( $data['multisite'] ) {
			EE::info( "Configure WPSC:\t\thttp://{$data['site_name']}/wp-admin/network/settings.php?page=wpsupercache" );
		} else {
			EE::info( "Configure WPSC:\t\thttp://{$data['site_name']}/wp-admin/options-general.php?page=wpsupercache" );
		}
	}

	if ( $data['wpredis'] ) {
		if ( $data['multisite'] ) {
			EE::info( "Configure redis-cache:\thttp://{$data['site_name']}/wp-admin/network/settings.php?page=redis-cache" );
		} else {
			EE::info( "Configure redis-cache:\thttp://{$data['site_name']}/wp-admin/options-general.php?page=redis-cache" );
			EE::info( "Object Cache:\t\tEnable" );
		}
	}

	if ( $data['wpfc'] || $data['w3tc'] ) {
		if ( $data['multisite'] ) {
			EE::info( "Configure W3TC:\t\thttp://{$data['site_name']}/wp-admin/network/admin.php?page=w3tc_general" );
		} else {
			EE::info( "Configure W3TC:\t\thttp://{$data['site_name']}/wp-admin/admin.php?page=w3tc_general" );
		}

		if ( $data['wpfc'] ) {
			EE::info( "Page Cache:\t\tDisable" );
		} else if ( $data['w3tc'] ) {
			EE::info( "Page Cache:\t\tDisk Enhanced" );
		}
		EE::info( "Database Cache:\t\tMemcached" );
		EE::info( "Object Cache:\t\tMemcached" );
		EE::info( "Browser Cache:\t\tDisable" );
	}
}

/**
 * Clone letsencrypt repo.
 *
 * @return bool
 */
function clone_lets_encrypt() {
	$letsencrypt_repo = "https://github.com/letsencrypt/letsencrypt";
	if ( ! ee_file_exists( "/opt" ) ) {
		ee_file_mkdir( "/opt" );
	}
	try {
		EE::info( "Downloading LetsEncrypt" );
		chdir( '/opt/' );
		EE::exec_cmd( "git clone {$letsencrypt_repo}" );
		EE::success( "[Done]" );

		return true;
	} catch ( Exception $e ) {
		EE::debug( $e->getMessage() );
		EE::info( "Unable to download file, LetsEncrypt" );

		return false;
	}
}

function renew_lets_encrypt( $ee_domain_name ) {
	$ee_wp_email = get_ee_config( 'user', 'email' );
	while( empty( $ee_wp_email)) {
		try {
			$ee_wp_email = EE::input_value( "Enter email address: " );
		} catch ( Exception $e ) {
			EE::debug( $e->getMessage() );
			EE::info( "Input WordPress email failed" );
		}
	}

	if ( ! ee_file_exists( "/opt/letsencrypt" ) ) {
		clone_lets_encrypt();
	}
	chdir( "/opt/letsencrypt" );
	EE::exec_cmd( "git pull" );
	EE::info( "Renewing SSl cert for https://{$ee_domain_name}" );
	$ssl = EE::exec_cmd( "./letsencrypt-auto --renew-by-default certonly --webroot -w /var/www/{$ee_wp_email}/htdocs/ -d {$ee_wp_email} -d www.{$ee_wp_email} --email {$ee_wp_email} --text --agree-tos" );
	$mail_list = '';
	if ( 0 !== $ssl ) {
		EE::error("ERROR : Cannot RENEW SSL cert !", false);
		$expiry_days = EE_Ssl::get_expiration_days( $ee_domain_name );
		if ( $expiry_days > 0 ){
			EE::error( "Your current cert will expire within {$expiry_days} days", false );
		} else {
			EE::error( "Your current cert already EXPIRED !", false );
		}
		$ee_subject = "[FAIL] SSL cert renewal {$ee_domain_name}";
		$ee_message = "Hey Hi,\n\nSSL Certificate renewal for https://{$ee_domain_name} was unsuccessful.";
		              $ee_message .= "\nPlease check easyengine log for reason. Your SSL Expiry date : ";
		$ee_message .= EE_Ssl::get_expiration_date( $ee_domain_name );
		$ee_message .= "\n\nFor support visit https://easyengine.io/support/ .\n\nYour's faithfully,\nEasyEngine";
		EE_Mail::send("easyengine@{$ee_domain_name}", $ee_subject, $ee_message, $ee_wp_email );
		EE::error( "Check logs for reason `tail /var/log/ee/ee.log` & Try Again!!!" );
	}

	EE_Git::add( array( "/etc/letsencrypt" ), "Adding letsencrypt folder" );

	$ee_subject = "[SUCCESS] SSL cert renewal {$ee_domain_name}";
	$ee_message = "Hey Hi,\n\nYour SSL Certificate has been renewed for https://{$ee_domain_name} .";
	$ee_message .= "\nYour SSL will Expire on : ";
	$ee_message .= EE_Ssl::get_expiration_date( $ee_domain_name );
	$ee_message .= "\n\nYour's faithfully,\nEasyEngine";
	EE_Mail::send("easyengine@{$ee_domain_name}", $ee_subject, $ee_message, $ee_wp_email );
}

/**
 * Setup letsencrypt ssl secure site.
 *
 * @param $ee_domain_name
 */
function setup_lets_encrypt( $ee_domain_name ) {
	$ee_wp_email = get_ee_git_config( 'user', 'email' );

	while ( empty( $ee_wp_email ) ) {
		try {
			$ee_wp_email = EE::input_value( 'Enter WordPress email: ' );
		} catch ( Exception $e ) {
			EE::debug( $e->getMessage() );
			EE::info( "input WordPress username failed" );
		}
	}

	if ( ! ee_file_exists( "/opt/letsencrypt" ) ) {
		clone_lets_encrypt();
	}
	chdir( '/opt/letsencrypt' );
	EE::exec_cmd( "git pull" );

	if ( ee_file_exists( "/etc/letsencrypt/renewal/{$ee_domain_name}.conf" ) ) {
		EE::debug( "LetsEncrypt SSL Certificate found for the domain {$ee_domain_name}" );
		$ssl = archived_certificate_handle( $ee_domain_name, $ee_wp_email );
	} else {
		EE::warning( "Please Wait while we fetch SSL Certificate for your site.\nIt may take time depending upon network." );
		$ssl = EE::exec_cmd( "./letsencrypt-auto certonly --webroot -w /var/www/{$ee_domain_name}/htdocs/ -d {$ee_domain_name} -d www.{$ee_domain_name} --email {$ee_wp_email} --text --agree-tos" );
		if ( 0 === $ssl ) {
			$ssl = true;
		}
	}

	if ( $ssl ) {
		EE::info( "Let's Encrypt successfully setup for your site" );
		EE::info( "Your certificate and chain have been saved at /etc/letsencrypt/live/{$ee_domain_name}/fullchain.pem" );
		EE::info( "Configuring Nginx SSL configuration" );
		try {
			EE::info( "Adding /var/www/{$ee_domain_name}/conf/nginx/ssl.conf" );
			$ssl_config_content = "listen 443 ssl http2;\n";
			$ssl_config_content .= "ssl on;\n";
			$ssl_config_content .= "ssl_certificate     /etc/letsencrypt/live/{0}/fullchain.pem;\n";
			$ssl_config_content .= "ssl_certificate_key     /etc/letsencrypt/live/{0}/privkey.pem;\n";
			ee_file_dump( "/var/www/{$ee_domain_name}/conf/nginx/ssl.conf", $ssl_config_content );
			EE_Git::add( array( "/etc/letsencrypt" ), "Adding letsencrypt folder" );
		} catch ( Exception $e ) {
			EE::debug( $e->getMessage() );
			EE::info( "Error occured while generating ssl.conf" );
		}
	} else {
		EE::error( "Unable to setup, Let's Encrypt", false );
		EE::error( "Please make sure that your site is pointed to \n" .
		           "same server on which you are running Let's Encrypt Client " .
		           "\n to allow it to verify the site automatically.", false );
	}
}

/**
 * Add https rediraction.
 *
 * @param      $ee_domain_name
 * @param bool $redirect
 */
function https_redirect( $ee_domain_name, $redirect = true ) {
	if ( $redirect ) {
		if ( ee_file_exists( "/etc/nginx/conf.d/force-ssl-{$ee_domain_name}.conf.disabled" ) ) {
			ee_file_rename( "/etc/nginx/conf.d/force-ssl-{$ee_domain_name}.conf.disabled", "/etc/nginx/conf.d/force-ssl-{$ee_domain_name}.conf" );
		} else {
			try {
				EE::info( "Adding /etc/nginx/conf.d/force-ssl-{$ee_domain_name}.conf" );
				$ssl_config_content = "server {\n".
                                     "\tlisten 80;\n".
                                     "\tserver_name www.{$ee_domain_name} {$ee_domain_name};\n".
                                     "\treturn 301 https://{$ee_domain_name}\$request_uri;\n}";
				ee_file_dump( "/etc/nginx/conf.d/force-ssl-{$ee_domain_name}.conf", $ssl_config_content );
			} catch ( Exception $e ) {
				EE::debug( $e->getMessage() );
				EE::info( "Error occured while generating /etc/nginx/conf.d/force-ssl-{$ee_domain_name}.conf" );
			}
		}
		EE::info( "Added HTTPS Force Redirection for Site http://{$ee_domain_name}" );
		EE_Git::add( array( "/etc/nginx" ), "Adding /etc/nginx/conf.d/force-ssl-{$ee_domain_name}.conf" );
	} else {
		if ( ee_file_exists( "/etc/nginx/conf.d/force-ssl-{$ee_domain_name}.conf" ) ) {
			ee_file_rename( "/etc/nginx/conf.d/force-ssl-{$ee_domain_name}.conf", "/etc/nginx/conf.d/force-ssl-{$ee_domain_name}.conf.disabled" );
			EE::info( "Disabled HTTPS Force Redirection for Site http://{$ee_domain_name}" );
		}
	}
}

/**
 * Archive certificates of site.
 *
 * @param $domain
 * @param $ee_wp_email
 *
 * @return bool|int|ProcessRun
 */
function archived_certificate_handle( $domain, $ee_wp_email ) {
	EE::info( "You already have an existing certificate for the domain requested.\n" .
	         "(ref: /etc/letsencrypt/renewal/{$domain}.conf)" .
	         "\nPlease select an option from below?" .
	         "\n\t1: Reinstall existing certificate" .
	         "\n\t2: Keep the existing certificate for now" .
	         "\n\t3: Renew & replace the certificate (limit ~5 per 7 days)" );
	$selected_option = EE::input_value( "\nType the appropriate number [1-3] or any other key to cancel: " );

	if ( ! ee_file_exists( "/etc/letsencrypt/live/{$domain}/cert.pem" ) ) {
		EE::error( "/etc/letsencrypt/live/{$domain}/cert.pem file is missing." );
	}

	switch ( $selected_option ) {
		case '1' :
			EE::info( "Please Wait while we reinstall SSL Certificate for your site.\nIt may take time depending upon network." );
			$ssl = EE::exec_cmd( "./letsencrypt-auto certonly --reinstall --webroot -w /var/www/{$domain}/htdocs/ -d {$domain} -d www.{$domain} --email {$ee_wp_email} --text --agree-tos" );
			if ( 0 === $ssl ) {
				$ssl = true;
			}
			break;
		case '2' :
			EE::info( "Using Existing Certificate files" );
			if ( ! ee_file_exists( "/etc/letsencrypt/live/{$domain}/fullchain.pem" ) || ! ee_file_exists( "/etc/letsencrypt/live/{$domain}/privkey.pem" ) ) {
				EE::error( "Certificate files not found. Skipping.\n" .
				           "Please check if following file exist\n\t/etc/letsencrypt/live/{0}/fullchain.pem\n\t" .
				           "/etc/letsencrypt/live/{0}/privkey.pem" );
			}
			$ssl = true;
			break;
		case '3' :
			EE::info( "Please Wait while we renew SSL Certificate for your site.\nIt may take time depending upon network." );
			$ssl = EE::exec_cmd( "./letsencrypt-auto --renew-by-default certonly --webroot -w /var/www/{$domain}/htdocs/ -d {$domain} -d www.{$domain} --email {$ee_wp_email} --text --agree-tos" );
			if ( 0 === $ssl ) {
				$ssl = true;
			}
			break;
		default :
			$ssl = false;
			EE::error( "Operation cancelled by user.", false );
	}

	if ( ee_file_exists( "{$domain}/conf/nginx/ssl.conf" ) ) {
		EE::info( "Existing ssl.conf . Backing it up .." );
		ee_file_rename( "/var/www/{$domain}/conf/nginx/ssl.conf/", "/var/www/{$domain}/conf/nginx/ssl.conf.bak" );
	}

	return $ssl;
}

function filter_site_assoc_args( $assoc_args ) {
	$sitetype  = $cachetype = '';
	$typelist  = array();
	$cachelist = array();

	foreach ( $assoc_args as $key => $val ) {
		if ( in_array( $key, array( 'html', 'php', 'mysql', 'wp', 'wpsubdir', 'wpsubdomain', 'php7' ) ) ) {
			$typelist[ $key ] = $val;
		} else if ( in_array( $key, array( 'wpfc', 'wpsc', 'w3tc', 'wpredis' ) ) ) {
			$cachelist[ $key ] = $val;
		}
	}

	if ( ! empty( $typelist ) || ! empty( $cachelist ) ) {
		if ( count( $cachelist ) > 1 ) {
			EE::error( "Could not determine cache type. Multiple cache parameter entered" );
		}
	}

	return array( $sitetype, $cachetype );
}

function update_wp_user_password( $ee_domain, $ee_site_webroot ) {
	$ee_wp_user = '';
	$ee_wp_pass = '';
	chdir( "{$ee_site_webroot}/htdocs/" );
	$is_wp = false;
	try {
		$is_wp = EE::exec_cmd( "wp --allow-root core version" );
		if ( 0 == $is_wp ) {
			$is_wp = true;
		}
	} catch ( Exception $e ) {
		EE::debug( $e->getMessage() );
		EE::info( "is WordPress site? check command failed " );
	}

	if ( ! $is_wp ) {
		EE::error("{$ee_domain} does not seem to be a WordPress site");
	}

	try {
		$ee_wp_user = EE::input_value( "Provide WordPress user name [admin]: " );
	} catch ( Exception $e ) {
		EE::debug( $e->getMessage() );
		EE::info( "\nCould not update password" );
	}

	if ( empty( $ee_wp_user ) ) {
		$ee_wp_user = 'admin';
	}

	try {
		$is_user_exist = EE::exec_cmd( "wp --allow-root user list --fields=user_login | grep {$ee_wp_user}" );
		if( 0 === $is_user_exist ) {
			$is_user_exist = true;
		}
	} catch ( Exception $e ) {
		EE::debug( $e->getMessage() );
		EE::info( "`if wp user exists check` command failed" );
	}

	if ( ! empty( $is_user_exist ) ) {
		try {
			while( empty( $ee_wp_pass ) ) {
				$ee_wp_pass = EE::input_hidden_value("Provide password for {$ee_wp_user} user: ");
			}
		} catch ( Exception $e ) {
			EE::debug( $e->getMessage() );
			EE::info( "failed to read password input " );
		}

		try {
			EE::exec_cmd( "wp --allow-root user update {$ee_wp_user} --user_pass={$ee_wp_pass}" );
		} catch ( Exception $e ) {
			EE::debug( $e->getMessage() );
			EE::info( "wp user password update command failed" );
		}
		EE::success( "Password updated successfully" );
	} else {
		EE::error( "Invalid WordPress user {$ee_wp_user} for {$ee_domain}." );
	}
}

function site_backup( $data ) {
	$ee_site_webroot = $data['webroot'];
	$backup_path = $ee_site_webroot . '/backup/' . EE_Variables::get_ee_date();
	if ( ! ee_file_exists( $backup_path )) {
		ee_file_mkdir( $backup_path );
	}
	EE::info("Backup location : {$backup_path}");
	ee_file_copy( "/etc/nginx/sites-available/{$data['site_name']}", $backup_path );

	if ( in_array( $data['currsitetype'], array( 'html', 'php', 'proxy', 'mysql' ) ) ) {
		if ( $data['php7'] && ! $data['wp'] ) {
			EE::info( "Backing up Webroot \t\t" );
			ee_file_copy( "{$ee_site_webroot}/htdocs", "{$backup_path}/htdocs" );
			EE::info( "[Done]" );
		} else {
			EE::info( "Backing up Webroot \t\t" );
			ee_file_rename( "{$ee_site_webroot}/htdocs", "{$backup_path}/htdocs" );
			EE::info( "[Done]" );
		}
	}

	$config_files = glob( $ee_site_webroot . '/*-config.php' );
	if ( empty( $config_files ) ) {
		EE::debug("Config files not found in {$ee_site_webroot}/ ");
		if ( 'mysql' !== $data['currsitetype'] ) {
			EE::debug("Searching wp-config.php in {$ee_site_webroot}/htdocs/ ");
			$config_files = glob( $ee_site_webroot . '/htdocs/wp-config.php' );
		}
	}

	if ( ! empty( $data['ee_db_name'] ) ) {
		EE::info( "Backing up database \t\t" );
		try {
			if ( 0 !== EE::exec_cmd( "mysqldump {$data['ee_db_name']} > {$backup_path}/{$data['ee_db_name']}.sql" ) ) {
				EE::info( "Fail" );
				EE::error( "mysqldump failed to backup database" );
			}
		} catch ( Exception $e ) {
			EE::debug( $e->getMessage() );
			EE::info( "mysqldump failed to backup database" );
		}
		EE::info( "Done" );

		if ( in_array( $data['currsitetype'], array( 'mysql', 'proxy' ) ) ) {
			if ( $data['php7'] && ! $data['wp'] ) {
				ee_file_copy( $config_files[0], $backup_path );
			} else {
				ee_file_rename( $config_files[0], $backup_path );
			}
		} else {
			ee_file_copy( $config_files[0], $backup_path );
		}
	}
}

function do_update_site( $site_name, $assoc_args ) {
	$hhvm = '';
	//	$pagespeed = '';
	$letsencrypt = false;
	$php7        = '';

	$ee_www_domain = EE_Utils::validate_domain( $site_name, false );
	$ee_domain     = EE_Utils::validate_domain( $site_name );

	if ( empty( $ee_domain ) ) {
		EE::error( 'Invalid domain name, Provide valid domain name' );
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

			$check_site = get_site_info( $site_name );

			if ( empty( $check_site ) ) {
				EE::error( "Site {$site_name} does not exist." );
			} else {
				//    old_pagespeed = $check_site['is_pagespeed'];
				$old_site_type     = $check_site['site_type'];
				$old_cache_type    = $check_site['cache_type'];
				$old_hhvm          = $check_site['is_hhvm'];
				$check_ssl         = $check_site['is_ssl'];
				$check_php_version = $check_site['php_version'];

				$data['currsitetype']  = $old_site_type;
				$data['currcachetype'] = $old_cache_type;

				if ( $check_php_version == "7.0" ) {
					$old_php7 = true;
				} else {
					$old_php7 = false;
				}
			}

			if ( ! empty( $assoc_args['password'] ) && empty( $assoc_args['type'] ) ) {
				try {
					update_wp_user_password( $ee_domain, $ee_site_webroot );
				} catch ( Exception $e ) {
					EE::debug( $e->getMessage() );
					EE::info( "Password Unchanged." );

					return false;
				}
			}

			if ( $stype === $old_site_type ) {
				EE::info( "Site is already in {$stype}" );
			}

			if ( 'proxy' === $old_site_type && ( ! empty( $assoc_args['hhvm'] ) || 'hhvm' === $stype ) ) {
				EE::info( "Can not update proxy site to HHVM" );
			}

			if ( 'html' === $old_site_type && ( ! empty( $assoc_args['hhvm'] ) || 'hhvm' === $stype ) ) {
				EE::info( "Can not update HTML site to HHVM" );
			}

			if ( ( 'php' === $stype && ! in_array( $old_site_type, array( 'html', 'proxy', 'php7' ) ) ) || ( 'mysql' === $stype && ! in_array( $old_site_type, array(
						'html',
						'php',
						'proxy',
						'php7'
					) ) ) || ( 'wp' === $stype && ! in_array( $old_site_type, array(
						'html',
						'php',
						'mysql',
						'proxy',
						'wp',
						'php7'
					) ) ) || ( 'wpsubdir' === $stype && ! in_array( $old_site_type, array( 'wpsubdomain' ) ) ) || ( 'wpsubdomain' === $stype && ! in_array( $old_site_type, array( 'wpsubdir' ) ) ) || ( $old_site_type === $stype && $old_cache_type === $cache )
			) {
				EE::info( "can not update {$old_site_type} {$old_cache_type} to {$stype} {$cache}" );
			}

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
			}

			if ( 'php' === $stype ) {
				$data['static']    = false;
				$data['basic']     = true;
				$data['wp']        = false;
				$data['w3tc']      = false;
				$data['wpfc']      = false;
				$data['wpsc']      = false;
				$data['wpredis']   = false;
				$data['multisite'] = false;
				$data['wpsubdir']  = false;
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
					$data['wp']    = true;
					$data['basic'] = false;
					$data['cache'] = true;
					if ( in_array( $stype, array( 'wpsubdir', 'wpsubdomain' ) ) ) {
						$data['multisite'] = true;
						if ( 'wpsubdir' === $stype ) {
							$data['wpsubdir'] = true;
						}
					}
				}
			}

			if ( 'php' === $stype || 'php7' === $stype || ! empty( $assoc_args['php'] ) ) {
				if ( empty( $data ) ) {
					$data  = array(
						'site_name'     => $ee_domain,
						'www_domain'    => $ee_www_domain,
						'currsitetype'  => $old_site_type,
						'currcachetype' => $old_cache_type,
						'webroot'       => $ee_site_webroot,
					);
					$stype = $old_site_type;
					$cache = $old_cache_type;
					if ( 'html' === $old_site_type || 'proxy' === $old_site_type ) {
						$data['static']    = true;
						$data['wp']        = false;
						$data['multisite'] = false;
						$data['wpsubdir']  = false;
					} else if ( 'php' === $old_site_type || 'mysql' === $old_site_type ) {
						$data['static']    = false;
						$data['wp']        = false;
						$data['multisite'] = false;
						$data['wpsubdir']  = false;
					} else if ( 'wp' === $old_site_type ) {
						$data['static']    = false;
						$data['wp']        = true;
						$data['multisite'] = false;
						$data['wpsubdir']  = false;
					} else if ( 'wpsubdir' === $old_site_type ) {
						$data['static']    = false;
						$data['wp']        = true;
						$data['multisite'] = true;
						$data['wpsubdir']  = true;
					} else if ( 'wpsubdomain' === $old_site_type ) {
						$data['static']    = false;
						$data['wp']        = true;
						$data['multisite'] = true;
						$data['wpsubdir']  = false;
					}

					if ( 'basic' === $old_cache_type ) {
						$data['basic']   = true;
						$data['w3tc']    = false;
						$data['wpfc']    = false;
						$data['wpsc']    = false;
						$data['wpredis'] = false;
					} else if ( 'w3tc' === $old_cache_type ) {
						$data['basic']   = false;
						$data['w3tc']    = true;
						$data['wpfc']    = false;
						$data['wpsc']    = false;
						$data['wpredis'] = false;
					} else if ( 'wpfc' === $old_cache_type ) {
						$data['basic']   = false;
						$data['w3tc']    = false;
						$data['wpfc']    = true;
						$data['wpsc']    = false;
						$data['wpredis'] = false;
					} else if ( 'wpsc' === $old_cache_type ) {
						$data['basic']   = false;
						$data['w3tc']    = false;
						$data['wpfc']    = false;
						$data['wpsc']    = true;
						$data['wpredis'] = false;
					} else if ( 'wpredis' === $old_cache_type ) {
						$data['basic']   = false;
						$data['w3tc']    = false;
						$data['wpfc']    = false;
						$data['wpsc']    = false;
						$data['wpredis'] = true;
					}
				}
				if ( '7.0' == $assoc_args['php'] || 'php7' === $stype ) {
					$data['php7']      = true;
					$php7              = true;
					$check_php_version = '7.0';
					if ( $old_php7 ) {
						EE::info( "PHP 7.0 is already enabled for given site" );
					}
				} else {
					$data['php7']      = false;
					$php7              = false;
					$check_php_version = '5.6';
					if ( ! $old_php7 ) {
						EE::info( "PHP 7.0 is already disabled for given site" );
					}
				}
			}

			if ( "renew" === $assoc_args['letsencrypt'] && empty( $assoc_args['all'] ) ) {
				$expiry_days     = EE_Ssl::get_expiration_days( $ee_domain );
				$min_expiry_days = 30;
				if ( $check_ssl ) {
					if ( $expiry_days <= $min_expiry_days ) {
						renew_lets_encrypt( $ee_domain );
					} else {
						EE::error( "More than 30 days left for certificate Expiry. Not renewing now." );
					}
				} else {
					EE::error( "Cannot RENEW ! SSL is not configured for given site ." );
				}

				if ( ! EE_Service::reload_service( "nginx" ) ) {
					EE::error( "service nginx reload failed. check issues with `nginx -t` command" );
				}
				EE::success( "Certificate was successfully renewed For https://{$ee_domain}" );
				$remaining_expiry_days = EE_Ssl::get_expiration_days( $ee_domain );
				$expiry_date           = EE_Ssl::get_expiration_date( $ee_domain );
				if ( $remaining_expiry_days > 0 ) {
					EE::info( "Your cert will expire within {$remaining_expiry_days}  days." );
					EE::info( "Expiration DATE: {$expiry_date}" );
				} else {
					EE::warning( "Your cert already EXPIRED !. PLEASE renew soon . " );
				}
			}

			if ( "renew" === $assoc_args['letsencrypt'] && ! empty( $assoc_args['all'] ) ) {
				if ( $check_ssl ) {
					$expiry_days = EE_Ssl::get_expiration_days( $ee_domain );
					if ( $expiry_days < 0 ) {
						return false;
					}
					$min_expiry_days = 30;
					if ( $expiry_days <= $min_expiry_days ) {
						renew_lets_encrypt( $ee_domain );
						if ( ! EE_Service::reload_service( "nginx" ) ) {
							EE::error( "service nginx reload failed. check issues with `nginx -t` command" );
						}
						EE::success( "Certificate was successfully renewed For https://{$ee_domain}" );
					} else {
						EE::error( "More than 30 days left for certificate Expiry. Not renewing now." );
					}

					$remaining_expiry_days = EE_Ssl::get_expiration_days( $ee_domain );
					$expiry_date           = EE_Ssl::get_expiration_date( $ee_domain );
					if ( $remaining_expiry_days > 0 ) {
						EE::info( "Your cert will expire within {$remaining_expiry_days}  days." );
						EE::info( "Expiration DATE: {$expiry_date}" );
					} else {
						return false;
					}
				} else {
					EE::error( "Cannot RENEW ! SSL is not configured for given site ." );
				}
			}

			if ( "off" === $assoc_args['letsencrypt'] && ! empty( $assoc_args['all'] ) ) {
				if ( ! $check_ssl ) {
					EE::error( "SSl is not configured for given " );
				}
			}

			if ( ! empty( $assoc_args['letsencrypt'] ) ) {
				if ( 'on' === $assoc_args['letsencrypt'] ) {
					$data['letsencrypt'] = true;
					$letsencrypt         = true;
					if ( $check_ssl ) {
						EE::error( "SSl is already configured for given site" );
					}
				} else if ( 'off' === $assoc_args['letsencrypt'] ) {
					$data['letsencrypt'] = false;
					$letsencrypt         = false;
					if ( ! $check_ssl ) {
						EE::error( "SSl is not configured for given site" );
					}
				}
			}

			if ( ! empty( $assoc_args['hhvm'] ) ) {
				if ( 'on' === $assoc_args['hhvm'] ) {
					if ( $old_hhvm ) {
						EE::info( "HHVM is already enable for given site" );
					}
				} else if ( 'off' === $assoc_args['hhvm'] ) {
					if ( ! $old_hhvm ) {
						EE::info( "HHVM is already disabled for given site" );
					}
				}
				$assoc_args['hhvm'] = false;
			}

			if ( ( ! empty( $data ) ) && empty( $assoc_args['hhvm'] ) ) {
				if ( $old_hhvm ) {
					$data['hhvm'] = true;
					$hhvm         = true;
				} else {
					$data['hhvm'] = false;
					$hhvm         = false;
				}
			}

			if ( ! empty( $data ) && ( ! $php7 ) ) {
				if ( $old_php7 ) {
					$data['php7'] = true;
					$php7         = true;
				} else {
					$data['php7'] = false;
					$php7         = false;
				}
			}


			if ( '7.0' === $assoc_args['php'] ) {
				$data['php7'] = true;
				$php7         = true;
			}

			if ( 'on' === $assoc_args['hhvm'] ) {
				$data['hhvm'] = true;
				$hhvm         = true;
			}

			if ( 'on' === $assoc_args['letsencrypt'] ) {
				$data['letsencrypt'] = true;
				$letsencrypt         = true;
			}

			if ( ( 'wpredis' === $stype || ! empty( $assoc_args['wpredis'] ) ) && 'wpredis' !== $data['currcachetype'] ) {
				$data['wpredis'] = false;
				$data['basic']   = true;
				$cache           = 'basic';
			}

			if ( empty( $data ) ) {
				EE::error( "Cannot update {$ee_domain}, Invalid Options" );
			}

			$ee_auth            = site_package_check( $stype );
			$data['ee_db_name'] = $check_site['db_name'];
			$data['ee_db_user'] = $check_site['db_user'];
			$data['ee_db_pass'] = $check_site['db_password'];
			$data['ee_db_host'] = $check_site['db_host'];

			if ( empty( $assoc_args['letsencrypt'] ) ) {
				try {
					pre_run_checks();
				} catch ( Exception $e ) {
					EE::debug( $e->getMessage() );
					EE::info( "NGINX configuration check failed." );
				}

				try {
					site_backup( $data );
				} catch ( Exception $e ) {
					EE::debug( $e->getMessage() );
					EE::info( "Check logs for reason `tail /var/log/ee/ee.log` & Try Again!!!" );
				}

				try {
					setup_domain( $data );
				} catch ( Exception $e ) {
					EE::debug( $e->getMessage() );
					EE::info( "Update site failed. Check logs for reason `tail /var/log/ee/ee.log` & Try Again!!!" );
				}
			}

			if ( ! empty( $data['proxy'] ) ) {
				update_site( $data, array( 'site_name' => $ee_domain ) );
				EE::info( "Successfully updated site http://{$ee_domain}" );
			}

			if ( ! empty( $assoc_args['letsencrypt'] ) ) {
				if ( true === $assoc_args['letsencrypt'] ) {
					if ( ! ee_file_exists( "{$ee_site_webroot}/conf/nginx/ssl.conf.disabled" ) ) {
						setup_lets_encrypt( $ee_domain );
					} else {
						ee_file_rename( "{$ee_site_webroot}/conf/nginx/ssl.conf.disabled", "{$ee_site_webroot}/conf/nginx/ssl.conf" );
					}
					https_redirect( $ee_domain );
					EE::info( "Creating Cron Job for cert auto-renewal" );
					EE_Cron::set_cron_weekly( 'ee site update --le=renew --all 2> /dev/null', 'Renew all letsencrypt SSL cert. Set by EasyEngine' );
					if ( ! EE_Service::reload_service( "nginx" ) ) {
						EE::error( "service nginx reload failed. check issues with `nginx -t` command" );
					}
					EE::success( "Congratulations! Successfully Configured SSl for Site https://{$ee_domain}" );
					$expiry_days = EE_Ssl::get_expiration_days( $ee_domain );
					if( $expiry_days > 0 ) {
						EE::info( "Your cert will expire within {$expiry_days} days" );
					} else {
						EE::warning( "Your cert already EXPIRED ! PLEASE renew soon." );
					}
				} else if ( false === $assoc_args['letsencrypt'] ) {
					if ( ee_file_exists( "{$ee_site_webroot}/conf/nginx/ssl.conf" ) ) {
						EE::info( "Setting Nginx configuration" );
						ee_file_rename( "{$ee_site_webroot}/conf/nginx/ssl.conf", "{$ee_site_webroot}/conf/nginx/ssl.conf.disabled" );
						https_redirect( $ee_domain, false );
						if ( ! EE_Service::reload_service( "nginx" ) ) {
							EE::error( "service nginx reload failed. check issues with `nginx -t` command" );
						}
						EE::success( "Successfully Disabled SSl for Site https://{$ee_domain}" );
					}
				}
				EE_Git::add( array( "{$ee_site_webroot}/conf/nginx/" ), "Adding letsencrypts config of site: {$ee_domain}" );
				update_site( $data, array( 'site_name' => $ee_domain ) );
				return 0;
			}

			if ( $stype === $old_site_type && $cache === $old_cache_type ) {
				if ( ! EE_Service::reload_service( "nginx" ) ) {
					EE::error( "service nginx reload failed. check issues with `nginx -t` command" );
				}
				update_site( $data, array( 'site_name' => $ee_domain ) );
				EE::info( "Successfully updated site http://{$ee_domain}" );
				return 0;
			}

			if ( ! empty( $data['ee_db_name'] ) && ( ! $data['wp'] ) ) {
				try {
					$data = setup_database( $data );
				} catch ( Exception $e ) {
					EE::debug( $e->getMessage() );
					EE::info( "Update site failed. Check logs for reason `tail /var/log/ee/ee.log` & Try Again!!!" );
				}
				try {
					$ee_db_config_content = "<?php \ndefine('DB_NAME', '{$data['ee_db_name']}');";
					$ee_db_config_content .= "\ndefine('DB_USER', '{$data['ee_db_user']}'); ";
					$ee_db_config_content .= "\ndefine('DB_PASSWORD', '{$data['ee_db_pass']}');";
					$ee_db_config_content .= "\ndefine('DB_HOST', '{$data['ee_db_host']}');\n?>";
					ee_file_dump( "{$ee_site_webroot}/ee-config.php", $ee_db_config_content );
				} catch ( Exception $e ) {
					EE::debug( $e->getMessage() );
					EE::debug("creating ee-config.php failed.");
					EE::info( "Update site failed. Check logs for reason `tail /var/log/ee/ee.log` & Try Again!!!" );
				}
			}

			if ( ! empty( $data['wp'] ) && in_array( $old_site_type, array( 'html', 'proxy', 'php', 'mysql' ) ) ) {
				try {
					$ee_wp_creds = setup_wordpress( $data );
				} catch ( Exception $e ) {
					EE::debug( $e->getMessage() );
					EE::info( "Update site failed. Check logs for reason `tail /var/log/ee/ee.log` & Try Again!!!" );
				}
			}

			if ( in_array( $old_site_type, array( 'wp', 'wpsubdir', 'wpsubdomain' ) ) ) {
				if ( ! empty( $data['multisite'] ) && 'wp' === $old_site_type ) {
					try {
						setup_wordpress_network( $data );
					} catch ( Exception $e ) {
						EE::debug( $e->getMessage() );
						EE::info( "Update site failed. Check logs for reason `tail /var/log/ee/ee.log` & Try Again!!!" );
					}
				}

				if ( 'w3tc' === $old_cache_type || 'wpfc' === $old_cache_type && ( ! $data['wpfc'] || ! $data['wpfc'] ) ) {
					try {
						uninstall_wp_plugin( 'w3-total-cache', $data );
					} catch ( Exception $e ) {
						EE::debug( $e->getMessage() );
						EE::info( "Update site failed. Check logs for reason `tail /var/log/ee/ee.log` & Try Again!!!" );
					}
				}

				if ( ( in_array( $old_cache_type, array( 'w3tc', 'wpsc', 'basic', 'wpredis' ) ) && $data['wpfc'] ) ||
				     ( 'wp' === $old_site_type && $data['multisite'] && $data['wpfc'] ) ) {
					try {
						$plugin_data = '{"log_level":"INFO","log_filesize":5,"enable_purge":1,"enable_map":0,"enable_log":0,';
						$plugin_data .= '"enable_stamp":0,"purge_homepage_on_new":1,"purge_homepage_on_edit":1,"purge_homepage_on_del":1,';
						$plugin_data .= '"purge_archive_on_new":1,"purge_archive_on_edit":0,"purge_archive_on_del":0,"purge_archive_on_new_comment":0,';
						$plugin_data .= '"purge_archive_on_deleted_comment":0,"purge_page_on_mod":1,"purge_page_on_new_comment":1,';
						$plugin_data .= '"purge_page_on_deleted_comment":1,"cache_method":"enable_fastcgi","purge_method":"get_request",';
						$plugin_data .= '"redis_hostname":"127.0.0.1","redis_port":"6379","redis_prefix":"nginx-cache:"}';
						setup_wp_plugin( 'nginx-helper', 'rt_wp_nginx_helper_options', $plugin_data, $data );
					} catch ( Exception $e ) {
						EE::debug( $e->getMessage() );
						EE::info( "Update site failed. Check logs for reason `tail /var/log/ee/ee.log` & Try Again!!!" );
						return 1;
					}
				} else if ( ( in_array( $old_cache_type, array( 'w3tc', 'wpsc', 'basic', 'wpfc' ) ) && $data['wpredis'] ) ||
				            ( 'wp' === $old_site_type && $data['multisite'] && $data['wpredis'] ) ) {
					try {
						$plugin_data = '{"log_level":"INFO","log_filesize":5,"enable_purge":1,"enable_map":0,"enable_log":0,';
						$plugin_data .= '"enable_stamp":0,"purge_homepage_on_new":1,"purge_homepage_on_edit":1,"purge_homepage_on_del":1,';
						$plugin_data .= '"purge_archive_on_new":1,"purge_archive_on_edit":0,"purge_archive_on_del":0,"purge_archive_on_new_comment":0,';
						$plugin_data .= '"purge_archive_on_deleted_comment":0,"purge_page_on_mod":1,"purge_page_on_new_comment":1,';
						$plugin_data .= '"purge_page_on_deleted_comment":1,"cache_method":"enable_redis","purge_method":"get_request",';
						$plugin_data .= '"redis_hostname":"127.0.0.1","redis_port":"6379","redis_prefix":"nginx-cache:"}';
						setup_wp_plugin( 'nginx-helper', 'rt_wp_nginx_helper_options', $plugin_data, $data );
					} catch ( Exception $e ) {
						EE::debug( $e->getMessage() );
						EE::info( "Update site failed. Check logs for reason `tail /var/log/ee/ee.log` & Try Again!!!" );
						return 1;
					}
				} else {
					try {
						$plugin_data = '{"log_level":"INFO","log_filesize":5,"enable_purge":0,"enable_map":0,';
						$plugin_data .= '"enable_log":0,"enable_stamp":0,"purge_homepage_on_new":1,"purge_homepage_on_edit":1,';
						$plugin_data .= '"purge_homepage_on_del":1,"purge_archive_on_new":1,"purge_archive_on_edit":0,"purge_archive_on_del":0,';
						$plugin_data .= '"purge_archive_on_new_comment":0,"purge_archive_on_deleted_comment":0,"purge_page_on_mod":1,';
						$plugin_data .= '"purge_page_on_new_comment":1,"purge_page_on_deleted_comment":1,"cache_method":"enable_redis",';
						$plugin_data .= '"purge_method":"get_request","redis_hostname":"127.0.0.1","redis_port":"6379","redis_prefix":"nginx-cache:"}';
						setup_wp_plugin( 'nginx-helper', 'rt_wp_nginx_helper_options', $plugin_data, $data );
					} catch ( Exception $e ) {
						EE::debug( $e->getMessage() );
						EE::info( "Update site failed. Check logs for reason `tail /var/log/ee/ee.log` & Try Again!!!" );
						return 1;
					}
				}

				if ( 'wpsc' === $old_cache_type && ( ! $data['wpsc'] ) ) {
					try {
						uninstall_wp_plugin( 'wp-super-cache', $data );
					} catch ( Exception $e ) {
						EE::debug( $e->getMessage() );
						EE::info( "Update site failed. Check logs for reason `tail /var/log/ee/ee.log` & Try Again!!!" );
						return 1;
					}
				}

				if ( 'wpredis' === $old_cache_type && ( ! $data['wpredis'] ) ) {
					try {
						uninstall_wp_plugin( 'redis-cache', $data );
					} catch ( Exception $e ) {
						EE::debug( $e->getMessage() );
						EE::info( "Update site failed. Check logs for reason `tail /var/log/ee/ee.log` & Try Again!!!" );
						return 1;
					}
				}
			}

			if ( ( 'w3tc' !== $old_cache_type || 'wpfc' !== $old_cache_type ) && ( $data['w3tc'] || $data['wpfc'] ) ) {
				try {
					install_wp_plugin( 'w3-total-cache', $data );
				} catch ( Exception $e ) {
					EE::debug( $e->getMessage() );
					EE::info( "Update site failed. Check logs for reason `tail /var/log/ee/ee.log` & Try Again!!!" );
				}
			}

			if ( 'wpsc' !== $old_cache_type && $data['wpsc'] ) {
				try {
					install_wp_plugin( 'w3-super-cache', $data );
				} catch ( Exception $e ) {
					EE::debug( $e->getMessage() );
					EE::info( "Update site failed. Check logs for reason `tail /var/log/ee/ee.log` & Try Again!!!" );
				}
			}

			if ( 'wpredis' !== $old_cache_type && $data['wpredis'] ) {
				try {
					if ( install_wp_plugin( 'redis-cache', $data ) ) {
						if ( ee_file_exists( "{$ee_site_webroot}/wp-config.php" ) ) {
							$config_path = "{$ee_site_webroot}/wp-config.php";
						} else if ( ee_file_exists( "{$ee_site_webroot}/htdocs/wp-config.php" ) ) {
							$config_path = "{$ee_site_webroot}/htdocs/wp-config.php";
						} else {
							EE::debug("Updating wp-config.php failed. File could not be located.");
							EE::error( "wp-config.php could not be located !!" );
							return false;
						}

						if ( ! grep_string( $config_path, "WP_CACHE_KEY_SALT" ) ) {
							$wp_config_content = "\n\ndefine('WP_CACHE_KEY_SALT', '{$ee_domain}:');";
							try {
								ee_file_append_content( $config_path, $wp_config_content );
							} catch ( Exception $e ) {
								EE::debug( $e->getMessage() );
								EE::debug( "Updating wp-config.php failed." );
								EE::warning("Updating wp-config.php failed. Could not append: \n {$wp_config_content} \n Please add manually.");
							}
						}
					}
				} catch ( Exception $e ) {
					EE::debug( $e->getMessage() );
					EE::info( "Update site failed. Check logs for reason `tail /var/log/ee/ee.log` & Try Again!!!" );
				}
			}

			if ( ! EE_Service::reload_service( "nginx" ) ) {
				EE::error( "service nginx reload failed. check issues with `nginx -t` command" );
			}

			EE_Git::add( array( "/etc/nginx" ), "{$ee_www_domain} updated with {$stype} {$cache}" );

			try {
				set_webroot_permissions( $data['webroot'] );
			} catch ( Exception $e ) {
				EE::debug( $e->getMessage() );
				EE::info( "Update site failed. Check logs for reason `tail /var/log/ee/ee.log` & Try Again!!!" );
			}

			if ( ! empty( $ee_auth ) ) {
				foreach ( $ee_auth as $msg ) {
					EE::info( $msg );
				}
			}

			display_cache_settings( $data );
			if ( $data['wp'] && in_array( $old_site_type, array( 'html', 'php', 'mysql' ) ) ) {
				EE::info( "\n\n WordPress admin user : {$ee_wp_creds['wp_user']}" );
				EE::info( "WordPress admin user : {$ee_wp_creds['wp_pass']}\n\n" );
			}
			if ( in_array( $old_site_type, array( 'html', 'php' ) ) && 'php' !== $stype ) {
				$data['db_name']     = $data['ee_db_name'];
				$data['db_user']     = $data['ee_db_user'];
				$data['db_password'] = $data['ee_db_pass'];
				$data['db_host']     = $data['ee_db_host'];
				$data['hhvm']        = $hhvm;
				$data['ssl']         = $check_site['is_ssl'];
				$data['php_version'] = $check_php_version;
				$update_data = update_site( $data, array( 'site_name' => $data['site_name'] ) );
			}
			EE::info( "Successfully updated site http://{$ee_domain}" );
		} else {
			//TODO: we will add hook for other packages. i.e do_action('update_site',$stype);
		}
	}
}