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
		EE::log( 'Writting the nginx configuration to file /etc/nginx/conf.d/blockips.conf' );
		EE\Utils\mustache_write_in_file( EE_NGINX_SITE_AVAIL_DIR . $ee_domain_name, $mustache_template, $data );
	} catch ( \Exception $e ) {
		EE::error( 'create nginx configuration failed for site' );
	} finally {
		try {
			EE::log( 'Checking generated nginx conf, please wait...' );
			EE::exec_cmd( "nginx -t", '', false, false );
			EE::debug( "[Done]" );
		} catch ( Exception $e ) {
			EE::debug( "[Fail]" );
			EE::error( "created nginx configuration failed for site. check with `nginx -t`" );
		}
	}

	$filesystem->symlink( EE_NGINX_SITE_AVAIL_DIR . $ee_domain_name, EE_NGINX_SITE_ENABLE_DIR . $ee_domain_name );

	if ( empty( $data['proxy'] ) ) {
		EE::log( 'Setting up webroot' );
		try {
			if ( ! ee_file_exists( "{$ee_site_webroot}/htdocs" ) ) {
				ee_file_mkdir("{$ee_site_webroot}/htdocs");
			}
			if ( ! ee_file_exists( "{$ee_site_webroot}/logs" ) ) {
				ee_file_mkdir("{$ee_site_webroot}/logs");
			}
			if ( ! ee_file_exists( "{$ee_site_webroot}/conf/nginx" ) ) {
				ee_file_mkdir("{$ee_site_webroot}/conf/nginx");
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
		EE_MySql::execute( "create user `{$ee_db_username}`@`{$ee_mysql_grant_host}` identified by '{$ee_db_password}'" );
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

	if ( in_array( $stype, array( 'html', 'proxy', 'php', 'mysql', 'wp', 'wpsubdir', 'wpsubdomain',	'php7' ) ) ) {
		EE::log( "Setting apt_packages variable for Nginx" );
		// Check if server has nginx-custom package.
		if ( ! EE_Apt_Get::is_installed( 'nginx-custom' ) || ! EE_Apt_Get::is_installed( 'nginx-mainline' ) ) {
			// Check if Server has nginx-plus installed.
			if ( EE_Apt_Get::is_installed( 'nginx-custom' ) ) {
				EE::log( "NGINX PLUS Detected ..." );
				$apt = EE_Variables::get_ee_nginx();
				$apt[] = "nginx-plus";
				ee_stack_post_pref($apt, $packages);
			} else if ( EE_Apt_Get::is_installed( 'nginx' ) ) {
				EE::log( "EasyEngine detected a previously installed Nginx package. " .
				         "It may or may not have required modules. " .
				         "\nIf you need help, please create an issue at https://github.com/EasyEngine/easyengine/issues/ \n" );
				$apt = EE_Variables::get_ee_nginx();
				$apt[] = "nginx";
				ee_stack_post_pref($apt, $packages);
			} else {
				$apt_packages = array_merge( $apt_packages, EE_Variables::get_ee_nginx() );
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
		EE::log("Setting apt_packages variable for PHP");
		if ( 'trusty' === $ee_platform_codename || 'xenial' === $ee_platform_codename ) {
			if ( ! EE_Apt_Get::is_installed( 'php5.6-fpm' ) ) {
				$apt_packages = array_merge( $apt_packages, EE_Variables::get_php_packages( 'php5.6' ), EE_Variables::get_php_packages( 'phpextra' ) );
			} else {
				if ( ! EE_Apt_Get::is_installed( 'php5-fpm' ) ) {
					$apt_packages = array_merge( $apt_packages, EE_Variables::get_php_packages( 'php' ) );
				}
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
			$apt_packages = array_merge( $apt_packages, EE_Variables::get_mysql_packages() );
			$packages = array_merge( $packages, array(
				"https://raw.githubusercontent.com/major/MySQLTuner-perl/master/mysqltuner.pl",
				"/usr/bin/mysqltuner",
				"MySQLTuner"
			) );
		}
	}

	if ( in_array( $stype, array( 'php', 'mysql', 'wp', 'wpsubdir', 'wpsubdomain' ) ) ) {
		EE::debug( "Setting apt_packages variable for Postfix" );
		if ( ! EE_Apt_Get::is_installed( 'postfix' ) ) {
			$apt_packages = array_merge( $apt_packages, EE_Variables::get_ee_postfix() );
		}
	}

	if ( in_array( $stype, array( 'wp', 'wpsubdir', 'wpsubdomain' ) ) ) {
		EE::debug( "Setting packages variable for WP-CLI" );
		if ( 0 !== EE::exec_cmd("which wp")) {
			$ee_wp_cli = EE_Variables::get_ee_wp_cli_version();
			$packages = array_merge( $packages, array(
				"https://github.com/wp-cli/wp-cli/releases/download/v{$ee_wp_cli}/wp-cli-{$ee_wp_cli}.phar",
				"/usr/bin/wp",
				"WP-CLI"
			) );
		}
	}

	if ( 'wpredis' === $stype ) {
		EE::debug("Setting apt_packages variable for redis");
	    if ( ! EE_Apt_Get::is_installed( 'redis-server' ) ) {
		    $apt_packages = array_merge( $apt_packages, EE_Variables::get_ee_redis_packages() );
	    }

	    if ( ee_file_exists("/etc/nginx/nginx.conf") && ! ee_file_exists("/etc/nginx/common/redis.conf")) {
	    	$data = array();
		    EE::debug( "Writting the nginx configuration to file /etc/nginx/common/redis.conf" );
		    \EE\Utils\mustache_write_in_file( '/etc/nginx/common/redis.conf', 'redis.mustache', $data );
	    }

	    if ( ee_file_exists("/etc/nginx/nginx.conf") && ! ee_file_exists("/etc/nginx/common/redis-hhvm.conf")) {
		    EE::debug( "Writting the nginx configuration to file /etc/nginx/common/redis-hhvm.conf" );
		    \EE\Utils\mustache_write_in_file( '/etc/nginx/common/redis-hhvm.conf', 'redis-hhvm.mustache' );
	    }
		if( ee_file_exists("/etc/nginx/conf.d/upstream.conf") ) {
			if ( ! grep_string('/etc/nginx/conf.d/upstream.conf', 'redis')) {
				$redis_upstream_content = "upstream redis {\n" .
			                              "    server 127.0.0.1:6379;\n" .
			                              "    keepalive 10;\n}";
				ee_file_append_content( "/etc/nginx/conf.d/upstream.conf", $redis_upstream_content );
			}
		}
		if( ee_file_exists("/etc/nginx/conf.d/upstream.conf") && ! ee_file_exists("/etc/nginx/conf.d/redis.conf") ) {
			$redis_config_content  = "# Log format Settings\n".
                                 "log_format rt_cache_redis '\$remote_addr \$upstream_response_time \$srcache_fetch_status [\$time_local] '\n".
                                 "'\$http_host \"\$request\" \$status \$body_bytes_sent '\n".
                                 "'\"\$http_referer\" \"\$http_user_agent\"';\n";
			ee_file_append_content( "/etc/nginx/conf.d/redis.conf", $redis_config_content );
		}
	}

	if ( 'hhvm' === $stype ) {
		$platform_architecture = EE_OS::ee_platform_architecture();
		if ( 'i686' === $platform_architecture || 'i386' === $platform_architecture ) {
			EE::error( "HHVM is not supported by 32bit system" );
		}
		EE::debug( "Setting apt_packages variable for HHVM" );
		if ( ! EE_Apt_Get::is_installed( 'hhvm' ) ) {
			$apt_packages = array_merge( $apt_packages, EE_Variables::get_hhvm_packages() );
		}

		if ( ee_file_exists( "/etc/nginx/common" ) && ! ee_file_exists( "/etc/nginx/common/php-hhvm.conf" ) ) {
			EE::debug("Writting the nginx configuration to file /etc/nginx/common/php-hhvm.conf");
			\EE\Utils\mustache_write_in_file( '/etc/nginx/common/php-hhvm.conf', 'php-hhvm.mustache' );

			EE::debug( "Writting the nginx configuration to file /etc/nginx/common/w3tc-hhvm.conf" );
			\EE\Utils\mustache_write_in_file( '/etc/nginx/common/w3tc-hhvm.conf', 'w3tc-hhvm.mustache' );

			EE::debug( "Writting the nginx configuration to file /etc/nginx/common/wpfc-hhvm.conf" );
			\EE\Utils\mustache_write_in_file( '/etc/nginx/common/wpfc-hhvm.conf', 'wpfc-hhvm.mustache' );

			EE::debug( "Writting the nginx configuration to file /etc/nginx/common/wpsc-hhvm.conf" );
			\EE\Utils\mustache_write_in_file( '/etc/nginx/common/wpsc-hhvm.conf', 'wpsc-hhvm.mustache' );
		}

		if ( ee_file_exists( "/etc/nginx/conf.d/upstream.conf" ) ) {
			if ( ! grep_string( "/etc/nginx/conf.d/upstream.conf", "hhvm" ) ) {
				$upstream_config_content = "upstream hhvm {\nserver 127.0.0.1:8000;\nserver 127.0.0.1:9000 backup;\n}\n";
				ee_file_append_content( "/etc/nginx/conf.d/upstream.conf", $upstream_config_content );
			}
		}
	}

	if ( 'php7' === $stype ) {
		$ee_platform_codename = EE_OS::ee_platform_codename();
		if ('wheezy' === $ee_platform_codename || 'precise' === $ee_platform_codename ){
			EE::error( "PHP 7.0 is not supported in your Platform" );
		}

		EE::debug( "Setting apt_packages variable for PHP 7.0" );
		if ( ! EE_Apt_Get::is_installed( 'php7.0-fpm' ) ) {
			$apt_packages = array_merge( $apt_packages, EE_Variables::get_php_packages( 'php7.0' ), EE_Variables::get_php_packages( 'phpextra' ) );
		}

		if ( ee_file_exists( "/etc/nginx/common" ) && ! ee_file_exists( "/etc/nginx/common/php7.conf" ) ) {
			EE::debug( "Writting the nginx configuration to file /etc/nginx/common/locations-php7.conf" );
			\EE\Utils\mustache_write_in_file( "/etc/nginx/common/locations-php7.conf", 'locations-php7.mustache' );

			EE::debug( "Writting the nginx configuration to file /etc/nginx/common/php7.conf" );
			\EE\Utils\mustache_write_in_file( "/etc/nginx/common/php7.conf", 'php7.mustache' );

			EE::debug( "Writting the nginx configuration to file /etc/nginx/common/w3tc-php7.conf" );
			\EE\Utils\mustache_write_in_file( "/etc/nginx/common/w3tc-php7.conf", 'wpfc-php7.mustache' );

			EE::debug( "Writting the nginx configuration to file /etc/nginx/common/wpsc-php7.conf" );
			\EE\Utils\mustache_write_in_file( "/etc/nginx/common/wpsc-php7.conf", 'wpsc-php7.mustache' );
		}

		if ( ee_file_exists( "/etc/nginx/nginx.conf" ) && ! ee_file_exists( "/etc/nginx/common/redis-php7.conf" ) ) {
			EE::debug( "Writting the nginx configuration to file /etc/nginx/common/redis-php7.conf" );
			\EE\Utils\mustache_write_in_file( "/etc/nginx/common/redis-php7.conf", 'redis-php7.mustache' );
		}

		if ( ee_file_exists( "/etc/nginx/conf.d/upstream.conf" ) ) {
			if ( grep_string( "/etc/nginx/conf.d/upstream.conf", "php7" ) ) {
				$upstream_config_content = "upstream php7 {\nserver 127.0.0.1:9070;\n}\n" .
				                           "upstream debug7 {\nserver 127.0.0.1:9170;\n}\n";
				ee_file_append_content( "/etc/nginx/conf.d/upstream.conf", $upstream_config_content );
			}
		}
	}

	$install_packages = ee_stack_install( $apt_packages, $packages );
	return $install_packages;
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

	EE::debug( "Done" );

	if ( empty( $data['ee_db_name'] ) && empty( $data['ee_db_user'] ) && empty( $data['ee_db_pass'] ) ) {
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
		$generate_config_cmd = "bash -c \"php {$ee_wp_cli_path} --allow-root core config --dbname='{$data['ee_db_name']}'";
		$generate_config_cmd .= " --dbprefix='{$ee_wp_prefix}' --dbuser='{$data['ee_db_user']}' --dbhost='{$data['ee_db_host']}'";
		$generate_config_cmd .= " --dbpass='{$data['ee_db_pass']}' --extra-php <<PHP\ndefine('WP_DEBUG', false); {$wpredis} \nPHP\"";
		EE::debug( $generate_config_cmd );

		try {

			$generate_config = EE::exec_cmd( $generate_config_cmd );

			if ( 0 != $generate_config ) {
				EE::log( "Generate wp-config failed for wp single site" );
			}
		} catch ( Exception $e ) {
			EE::debug( $e->getMessage() );
			EE::log( "Generate wp-config failed for wp single site" );
		}
	} else {
		EE::log( "Generating wp-config for WordPress multisite" );
		$generate_config_cmd = "bash -c \"php {$ee_wp_cli_path} --allow-root core config --dbname='{$data['ee_db_name']}'";
		$generate_config_cmd .= " --dbprefix='{$ee_wp_prefix}' --dbuser='{$data['ee_db_user']}' --dbhost='{$data['ee_db_host']}'";
		$generate_config_cmd .= " --dbpass='{$data['ee_db_pass']}' --extra-php <<PHP\n\ndefine('WP_ALLOW_MULTISITE', true);";
		$generate_config_cmd .= "\n\ndefine('WPMU_ACCEL_REDIRECT', true);\n\ndefine('WP_DEBUG', false); {$wpredis} \nPHP\"";


		EE::debug( $generate_config_cmd );

		try {

			$generate_config = EE::exec_cmd( $generate_config_cmd );

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
		EE::log( "Update wordpress permalinks failed" );
	}

	// Install Wp Super Cache.
	if ( $data['wpsc'] ) {
		install_wp_plugin( 'wp-super-cache', $data );
	}

	// Install Redis Cache.
	if ( $data['wpredis'] ) {
		install_wp_plugin( 'redis-cache', $data );
	}

	// Install W3 Total Cache.
	if ( $data['w3tc'] || $data['wpfc'] ) {
		install_wp_plugin( 'w3-total-cache', $data );
	}

	$wp_creds = array(
		'wp_user'  => $ee_wp_user,
		'wp_pass'  => $ee_wp_pass,
		'wp_email' => $ee_wp_email
	);

	return $wp_creds;

}

function install_wp_plugin( $plugin_name, $data ) {
	$ee_site_webroot = $data['webroot'];
	EE::log( "Installing plugin {$plugin_name}, please wait..." );
	chdir( "{$ee_site_webroot}/htdocs/" );
	$ee_wpcli_path = EE_Variables::get_ee_wp_cli_path();
	try {
		EE::exec_cmd( "php {$ee_wpcli_path} plugin --allow-root install {$plugin_name}" );
	} catch ( Exception $e ) {
		EE::debug( $e->getMessage() );
		EE::log( "plugin installation failed" );
	}
	try {
		$network = $data['multisite'] ? '--network' : '';
		EE::exec_cmd( "php {$ee_wpcli_path} plugin --allow-root activate {$plugin_name} {$network}" );
	} catch ( Exception $e ) {
		EE::debug( $e->getMessage() );
		EE::log( "plugin activation failed" );
	}
}

function uninstall_wp_plugin( $plugin_name, $data ) {
	$ee_site_webroot = $data['webroot'];
	EE::log( "Uninstalling plugin {$plugin_name}, please wait..." );
	chdir( "{$ee_site_webroot}/htdocs/" );
	EE::log( "Uninstalling plugin {$plugin_name}, please wait..." );
	$ee_wpcli_path = EE_Variables::get_ee_wp_cli_path();
	try {
		EE::exec_cmd( "php {$ee_wpcli_path} plugin --allow-root deactivate {$plugin_name}" );
		EE::exec_cmd( "php {$ee_wpcli_path} plugin --allow-root uninstall {$plugin_name}" );
	} catch ( Exception $e ) {
		EE::debug( $e->getMessage() );
		EE::log( "plugin uninstall failed" );
	}
}

function set_webroot_permissions( $webroot ) {
	EE::log( "Setting up permissions" );
	$ee_php_user = EE_Variables::get_ee_php_user();
	try {
		ee_file_chown( $webroot, $ee_php_user, true );
	} catch ( Exception $e ) {
		EE::debug( $e->getMessage() );
		EE::log( "problem occured while setting up webroot permissions" );
	}
}

function display_cache_settings( $data ) {
	if ( $data['wpsc'] ) {
		if ( $data['multisite'] ) {
			EE::log( "Configure WPSC:\t\thttp://{$data['site_name']}/wp-admin/network/settings.php?page=wpsupercache" );
		} else {
			EE::log( "Configure WPSC:\t\thttp://{$data['site_name']}/wp-admin/options-general.php?page=wpsupercache" );
		}
	}

	if ( $data['wpredis'] ) {
		if ( $data['multisite'] ) {
			EE::log( "Configure redis-cache:\thttp://{$data['site_name']}/wp-admin/network/settings.php?page=redis-cache" );
		} else {
			EE::log( "Configure redis-cache:\thttp://{$data['site_name']}/wp-admin/options-general.php?page=redis-cache" );
			EE::log( "Object Cache:\t\tEnable" );
		}
	}

	if ( $data['wpfc'] || $data['w3tc'] ) {
		if ( $data['multisite'] ) {
			EE::log( "Configure W3TC:\t\thttp://{$data['site_name']}/wp-admin/network/admin.php?page=w3tc_general" );
		} else {
			EE::log( "Configure W3TC:\t\thttp://{$data['site_name']}/wp-admin/admin.php?page=w3tc_general" );
		}

		if ( $data['wpfc'] ) {
			EE::log( "Page Cache:\t\tDisable" );
		} else if ( $data['w3tc'] ) {
			EE::log( "Page Cache:\t\tDisk Enhanced" );
		}
		EE::log( "Database Cache:\t\tMemcached" );
		EE::log( "Object Cache:\t\tMemcached" );
		EE::log( "Browser Cache:\t\tDisable" );
	}
}

function clone_lets_encrypt() {
	$letsencrypt_repo = "https://github.com/letsencrypt/letsencrypt";
	if ( ! ee_file_exists( "/opt" ) ) {
		ee_file_mkdir( "/opt" );
	}
	try {
		EE::log( "Downloading LetsEncrypt" );
		chdir( '/opt/' );
		EE::exec_cmd( "git clone {$letsencrypt_repo}" );
		EE::success( "[Done]" );

		return true;
	} catch ( Exception $e ) {
		EE::debug( $e->getMessage() );
		EE::log( "Unable to download file, LetsEncrypt" );

		return false;
	}
}

function setup_lets_encrypt( $ee_domain_name ) {
	$ee_wp_email = get_ee_git_config( 'user', 'email' );

	while ( empty( $ee_wp_email ) ) {
		try {
			$ee_wp_email = EE::input_value( 'Enter WordPress email: ' );
		} catch ( Exception $e ) {
			EE::debug( $e->getMessage() );
			EE::log( "input WordPress username failed" );
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
		EE::log( "Let's Encrypt successfully setup for your site" );
		EE::log( "Your certificate and chain have been saved at /etc/letsencrypt/live/{$ee_domain_name}/fullchain.pem" );
		EE::log( "Configuring Nginx SSL configuration" );
		try {
			EE::log( "Adding /var/www/{$ee_domain_name}/conf/nginx/ssl.conf" );
			$ssl_config_content = "listen 443 ssl http2;\n";
			$ssl_config_content .= "ssl on;\n";
			$ssl_config_content .= "ssl_certificate     /etc/letsencrypt/live/{0}/fullchain.pem;\n";
			$ssl_config_content .= "ssl_certificate_key     /etc/letsencrypt/live/{0}/privkey.pem;\n";
			ee_file_dump( "/var/www/{$ee_domain_name}/conf/nginx/ssl.conf", $ssl_config_content );
			EE_Git::add( array( "/etc/letsencrypt" ), "Adding letsencrypt folder" );
		} catch ( Exception $e ) {
			EE::debug( $e->getMessage() );
			EE::log( "Error occured while generating ssl.conf" );
		}
	} else {
		EE::error( "Unable to setup, Let's Encrypt", false );
		EE::error( "Please make sure that your site is pointed to \n" .
		           "same server on which you are running Let's Encrypt Client " .
		           "\n to allow it to verify the site automatically.", false );
	}
}

function https_redirect( $ee_domain_name, $redirect = true ) {
	if ( $redirect ) {
		if ( ee_file_exists( "/etc/nginx/conf.d/force-ssl-{$ee_domain_name}.conf.disabled" ) ) {
			ee_file_rename( "/etc/nginx/conf.d/force-ssl-{$ee_domain_name}.conf.disabled", "/etc/nginx/conf.d/force-ssl-{$ee_domain_name}.conf" );
		} else {
			try {
				EE::log( "Adding /etc/nginx/conf.d/force-ssl-{$ee_domain_name}.conf" );
				$ssl_config_content = "server {\n".
                                     "\tlisten 80;\n".
                                     "\tserver_name www.{$ee_domain_name} {$ee_domain_name};\n".
                                     "\treturn 301 https://{$ee_domain_name}\$request_uri;\n}";
				ee_file_dump( "/etc/nginx/conf.d/force-ssl-{$ee_domain_name}.conf", $ssl_config_content );
			} catch ( Exception $e ) {
				EE::debug( $e->getMessage() );
				EE::log( "Error occured while generating /etc/nginx/conf.d/force-ssl-{$ee_domain_name}.conf" );
			}
		}
		EE::log("Added HTTPS Force Redirection for Site http://{$ee_domain_name}");
		EE_Git::add( array( "/etc/nginx" ), "Adding /etc/nginx/conf.d/force-ssl-{$ee_domain_name}.conf" );
	} else {
		if ( ee_file_exists( "/etc/nginx/conf.d/force-ssl-{$ee_domain_name}.conf" ) ) {
			ee_file_rename( "/etc/nginx/conf.d/force-ssl-{$ee_domain_name}.conf", "/etc/nginx/conf.d/force-ssl-{$ee_domain_name}.conf.disabled" );
			EE::log( "Disabled HTTPS Force Redirection for Site http://{$ee_domain_name}" );
		}
	}
}

function archived_certificate_handle( $domain, $ee_wp_email ) {
	EE::log( "You already have an existing certificate for the domain requested.\n" .
	         "(ref: /etc/letsencrypt/renewal/{$domain}.conf)" .
	         "\nPlease select an option from below?" .
	         "\n\t1: Reinstall existing certificate" .
	         "\n\t2: Keep the existing certificate for now" .
	         "\n\t3: Renew & replace the certificate (limit ~5 per 7 days)" );
	$selected_option = EE::input_value( "\nType the appropriate number [1-3] or any other key to cancel: " );

	if ( ! ee_file_exists("/etc/letsencrypt/live/{$domain}/cert.pem")) {
		EE::error("/etc/letsencrypt/live/{$domain}/cert.pem file is missing.");
	}

	switch ( $selected_option ) {
		case '1' :
			EE::log("Please Wait while we reinstall SSL Certificate for your site.\nIt may take time depending upon network.");
			$ssl = EE::exec_cmd( "./letsencrypt-auto certonly --reinstall --webroot -w /var/www/{$domain}/htdocs/ -d {$domain} -d www.{$domain} --email {$ee_wp_email} --text --agree-tos" );
			if ( 0 === $ssl ) {
				$ssl = true;
			}
			break;
		case '2' :
			EE::log("Using Existing Certificate files");
			if ( ! ee_file_exists( "/etc/letsencrypt/live/{$domain}/fullchain.pem" ) || ! ee_file_exists( "/etc/letsencrypt/live/{$domain}/privkey.pem" ) ) {
				EE::error( "Certificate files not found. Skipping.\n" .
				           "Please check if following file exist\n\t/etc/letsencrypt/live/{0}/fullchain.pem\n\t" .
				           "/etc/letsencrypt/live/{0}/privkey.pem" );
			}
			$ssl = true;
			break;
		case '3' :
			EE::log("Please Wait while we renew SSL Certificate for your site.\nIt may take time depending upon network.");
			$ssl = EE::exec_cmd("./letsencrypt-auto --renew-by-default certonly --webroot -w /var/www/{$domain}/htdocs/ -d {$domain} -d www.{$domain} --email {$ee_wp_email} --text --agree-tos");
			if ( 0 === $ssl ) {
				$ssl = true;
			}
			break;
		default :
			$ssl = false;
			EE::error( "Operation cancelled by user.", false );
	}

	if ( ee_file_exists("{$domain}/conf/nginx/ssl.conf")) {
		EE::log("Existing ssl.conf . Backing it up ..");
		ee_file_rename( "/var/www/{$domain}/conf/nginx/ssl.conf/", "/var/www/{$domain}/conf/nginx/ssl.conf.bak" );
	}
	return $ssl;
}