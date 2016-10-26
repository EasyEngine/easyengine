<?php

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;


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
class Stack_Command extends EE_Command {

	/**
	 * Install Stack.
	 *
	 * ## OPTIONS
	 *
	 *[--all]
	 *:all
	 * [--web]
	 * : To install web.
	 *
	 * [--admin]
	 *
	 * [--nginx]
	 * : To install nginx.
	 *
	 * [--php]
	 * : To install php.
	 *
	 * [--mysql]
	 * : To install MySQL.
	 *
	 *
	 * ## EXAMPLES
	 *
	 *      # Install Stack.
	 *      $ ee stack install --nginx
	 *
	 */
	public function install( $args, $assoc_args ) {

		list( $site_name ) = $args;
		$apt_packages = array();
		$packages = array();
		$stack = array();
		if(!empty($assoc_args['nginx'])){
			$stack['nginx']= true;
		}

		if (!empty($assoc_args['no_diplay_message'])){
			$disp_msg = false;
		}else{
			$disp_msg = true;
		}

		if( !empty( $assoc_args['pagespeed'] ) ) {
			EE::error( $site_name . 'Pagespeed support has been dropped since EasyEngine v3.6.0' );
			EE::error( $site_name . 'Please run command again without `--pagespeed`' );
			EE::error( $site_name . 'For more details, read - https://easyengine.io/blog/disabling-pagespeed/' );
		}

	if(!empty( $assoc_args['all'] )){
		$category['web'] = True;
		$category['admin'] = True;
	}

	if ($category['web'] == true){
			$stack['nginx']= true;
			$stack['php']= true;
			$stack['mysql']= true;
			$stack['wpcli']= true;
			$stack['postfix']= true;
	}
	if ($category['admin'] == true){
		$stack['nginx']= true;
		$stack['php']= true;
		$stack['mysql']= true;
		$stack['adminer']= true;
		$stack['phpmyadmin']= true;
		$category['utils']= true;
	}

	// if ($category['mail'] == true){
	// todo:
	// }

	if (!empty($stack['redis'])) {
		if ( ! EE_Apt_Get::is_installed( 'redis-server' ) ) {

			$apt_packages = array_merge( $apt_packages, EE_Variables::get_redis_packages() );
		} else {
			EE::success( "Redis already installed" );
		}
	}

	if ($stack['nginx']){

		EE::log("Setting apt_packages variable for Nginx");
		if(!EE_Apt_Get::is_installed('nginx-custom')){
			if(!(EE_Apt_Get::is_installed('nginx-plus')||EE_Apt_Get::is_installed('nginx'))){
				$apt_packages = array_merge($apt_packages,EE_Variables::get_nginx_packages(  ));
			}else{
					if(EE_Apt_Get::is_installed('nginx-plus')){
						EE::success("NGINX PLUS Detected ...");
						$apt[]="nginx-plus";
						$apt=array_merge($apt,EE_Variables::get_nginx_packages());
						self::post_pref($apt, $packages);
					}elseif(EE_Apt_Get::is_installed('nginx')){
						EE:success("EasyEngine detected a previously installed Nginx package. ".
						"It may or may not have required modules. ".
						"\nIf you need help, please create an issue at https://github.com/EasyEngine/easyengine/issues/ \n");
						$apt[]="nginx";
						$apt=array_merge($apt,EE_Variables::get_nginx_packages());
						self::post_pref($apt, $packages);
					}
			}
		}else{
			EE::log("Nginx Stable already installed");
		}
	}
	if (!empty($stack['php'])){
		EE::debug("Setting apt_packages variable for PHP");
		if(!(EE_Apt_Get::is_installed('php5-fpm')||EE_Apt_Get::is_installed('php5.6-fpm'))){
			if(EE_OS::ee_platform_codename() == 'trusty'||EE_OS::ee_platform_codename() == 'xenial'){
				$apt_packages = array_merge($apt_packages,EE_Variables::get_package_list('php5.6'),EE_Variables::get_package_list('phpextra'));
			}else{
				$apt_packages = array_merge($apt_packages,EE_Variables::get_php_packages( 'php' ));
			}
		}else{
			EE::success("PHP already installed");
		}
	}

	if ( EE_OS::ee_platform_distro() == 'debian' && !empty($stack['php'])){
		if (EE_OS::ee_platform_codename() == 'jessie'){
			EE::debug("Setting apt_packages variable for PHP 7.0");
			if(!EE_Apt_Get::is_installed('php7.0-fpm')){
				$apt_packages = array_merge($apt_packages,EE_Variables::get_package_list('php7.0'));
				if(!EE_Apt_Get::is_installed('php5-fpm')){
					$apt_packages = array_merge($apt_packages,EE_Variables::get_package_list('php'));
				}
			}else{
				EE::success("PHP 7.0 already installed");
			}
		}
	}


	if (!empty($stack['php'] && !EE_OS::ee_platform_codename() == 'debian')){
		if (EE_OS::ee_platform_codename() == 'trusty'||EE_OS::ee_platform_codename() == 'xenial'){
			EE::debug("Setting apt_packages variable for PHP 7.0");
			if(!EE_Apt_Get::is_installed('php7.0-fpm')){
				$apt_packages = array_merge($apt_packages,EE_Variables::get_package_list('php7.0'));
				if(!EE_Apt_Get::is_installed('php5.6-fpm')){
					$apt_packages = array_merge($apt_packages,EE_Variables::get_package_list('php5.6'),EE_Variables::get_package_list('phpextra'));
				}
			}else{
				EE::success("PHP 7.0 already installed");
			}
		}
	}

	if (!empty($stack['mysql'])){
		EE::debug("Setting apt_packages variable for MySQL");
		if (!EE::exec_cmd("mysqladmin ping", $message = 'Looking for active mysql connection')){
			$apt_packages = array_merge($apt_packages,EE_Variables::get_mysql_packages());
			$packages = array_merge($packages, array("mysqltunner"));
		}else{
			EE::success("MySQL connection is already alive");
		}
	}


	if (!empty($stack['postfix'])){
		EE::debug("Setting apt_packages variable for Postfix");
		if(!EE_Apt_Get::is_installed('postfix')){
			$apt_packages = array_merge($apt_packages,EE_Variables::get_package_list('postfix'));
		}else{
			EE::success("Postfix is already installed");
		}
	}

	if (!empty($stack['wpcli'])){
		EE::debug("Setting packages variable for WP-CLI");
		if (!EE::exec_cmd("which wp", $message = 'Looking wp-cli preinstalled')){
			$packages = array_merge($packages, array("wpcli"));
		}
	else{
			EE::success("WP-CLI is already installed");
		}
	}

	if (!empty($stack['phpmyadmin'])){
		EE::debug("Setting packages variable for phpMyAdmin");
			$packages = array_merge($packages, array("phpmyadmin"));
	}

	if (!empty($stack['phpredisadmin'])){
		EE::debug("Setting packages variable for phpRedisAdmin");
			$packages = array_merge($packages, array("phpredisadmin"));
	}

	if (!empty($stack['adminer'])){
		EE::debug("Setting packages variable for Adminer");
			$packages = array_merge($packages, array("adminer"));
	}

	if (!empty($category['utils'])){
		EE::debug("Setting packages variable for utils");
			$packages = array_merge($packages, array("phpmemcacheadmin","opcache","rtcache-clean", "opcache-gui","ocp","webgrind","perconna-toolkit","anemometer"));
	}

	if(!empty($apt_packages)||!empty($packages)){
		EE::debug("Calling pre_pref");
		self::pre_pref($apt_packages);
		if(!empty($apt_packages)){
			EE_OS::add_swap();
			EE::success("Updating apt-cache, please wait...");
			EE_Apt_Get::update();
			EE_Apt_Get::install($apt_packages);
		}
		if(!empty($packages)){
			EE::debug("Downloading following: " .implode(' ',$packages));
			EE_Utils::download($packages);
		}
		EE::log("Calling post_pref");
		self::post_pref($apt_packages, $packages);
		if(in_array('redis-server',$apt_packages)){
			if (is_file("/etc/redis/redis.conf")){
				$system_mem_info = EE_OS::get_system_mem_info();
				if($system_mem_info['MemTotal'] < 512){
					EE::debug("Setting maxmemory variable to" . (int)$system_mem_info['MemTotal']*1024*1024*0.1. " in redis.conf");
					EE::exec_cmd_output("sed -i s/# maxmemory/maxmemory " . (int)$system_mem_info['MemTotal']*(1024*1024*0.1) ."/' /etc/redis/redis.conf", $message = '', $exit_on_error = false);
					EE::exec_cmd_output("sed -i 's/# maxmemory-policy.*/maxmemory-policy allkeys-lru/' /etc/redis/redis.conf", $message = 'Setting maxmemory-policy variable to allkeys-lru in redis.conf', $exit_on_error = false);
					EE_Service::restart_service( 'redis-server' );
				}
				else{
					EE::debug("Setting maxmemory variable to" . (int)$system_mem_info['MemTotal']*1024*1024*0.2 . " in redis.conf");
					EE::exec_cmd_output("sed -i s/# maxmemory/maxmemory " . (int)$system_mem_info['MemTotal']*(1024*1024*0.2) ."/' /etc/redis/redis.conf", $message = '', $exit_on_error = false);
					EE::exec_cmd_output("sed -i 's/# maxmemory-policy.*/maxmemory-policy allkeys-lru/' /etc/redis/redis.conf", $message = 'Setting maxmemory-policy variable to allkeys-lru in redis.conf', $exit_on_error = false);
					EE_Service::restart_service( 'redis-server' );
				}
			}
		}

		if ($disp_msg){
			EE::success("Successfully installed packages");
		}

	}

	}


	public static function pre_pref($apt_packages){


		// Pre settings to do before installation packages

		if (in_array(EE_Variables::get_package_list('postfix'), $apt_packages) ) {
			echo "Pre-seeding Postfix";
			try{

				EE::exec_cmd_output('echo "postfix postfix/main_mailer_type string \'Internet Site\'" | debconf-set-selections', $message = '', $exit_on_error = false);
				EE::exec_cmd_output('echo "postfix postfix/mailname string $(hostname -f)" | debconf-set-selections', $message = '', $exit_on_error = false);
			}catch(Exception $e){
				echo "Failed to intialize postfix package";
			}
		}

		if (in_array(EE_Variables::get_package_list('mysql'), $apt_packages) ) {
			EE::log("Adding repository for MySQL, please wait...");
			$mysql_pref = "Package: *\nPin: origin sfo1.mirrors.digitalocean.com\nPin-Priority: 1000\n";
			$mysql_pref_file   = fopen("/etc/apt/preferences.d/MariaDB.pref", "w" );
			fwrite( $mysql_pref_file, $mysql_pref );

			EE_Repo::add(EE_Variables::get_mysql_repo());

				EE::debug("Adding key for Mysql");
				if (EE_OS::ee_platform_codename() != 'xenial'){
					EE_Repo::add_key('0xcbcb082a1bb943db',"keyserver.ubuntu.com");

				}else {
					EE_Repo::add_key('0xF1656F24C74CD1D8',"keyserver.ubuntu.com");
				}

			$char = EE_Utils::random_string(8);
			EE::debug("Pre-seeding MySQL");
			EE::debug("echo \"mariadb-server-10.1 mysql-server/root_password " .
				"password \" | debconf-set-selections");
			$reset_pwd = 'echo "mariadb-server-10.1" "mysql-server/root_password_again" password ' . $char .' |  "debconf-set-selections" ';
			EE::exec_cmd($reset_pwd);
			$mysql_config = '[client]\n user = root\n password = '. $char;
            EE::debug('Writting configuration into MySQL file');
            $conf_path = "/etc/mysql/conf.d/my.cnf";
		    ee_file_dump($conf_path,$mysql_config);
			EE::debug("Setting my.cnf permission");
		    ee_file_chmod("/etc/mysql/conf.d/my.cnf",0600);

		}

		if ( in_array(EE_Variables::get_nginx_packages(), $apt_packages)) {
			EE::log("Adding repository for NGINX, please wait...");
			EE_Repo::add(EE_Variables::get_nginx_repo());
			EE_Repo::add_key('3050AC3CD2AE6F03');
		}

		if (in_array(EE_Variables::get_package_list('php7.0'), $apt_packages) || in_array(EE_Variables::get_package_list('php5.6'), $apt_packages)) {
				EE::log("Adding repository for PHP, please wait...");
			    EE_Repo::add(EE_Variables::get_php_repo());
			if ('debian' == EE_OS::ee_platform_distro()){
				EE_Repo::add_key('89DF5277');
				}
		}
		if ( in_array(EE_Variables::get_redis_packages(), $apt_packages)) {
			EE::log("Adding repository for REDIS, please wait...");
			EE_Repo::add(EE_Variables::get_redis_repo());
			if ('debian' == EE_OS::ee_platform_distro()){
				EE_Repo::add_key('3050AC3CD2AE6F03');
			}
		}

	}

	public static function post_pref($apt_packages,$packages){
		//Post activity after installation of packages

		$filesystem = new Filesystem();
		//Post activity after installation of packages
		if (! empty( $apt_packages )) {
			if (!empty(array_intersect(array('postfix'), $apt_packages))) {
				EE_Git::add("/etc/postfix", "Adding Postfix into Git");
				EE_Service::reload_service('postfix');
			}

			if (!empty(array_intersect(EE_Variables::get_nginx_packages(), $apt_packages))) {
				if ( in_array( "nginx-plus", $apt_packages ) || in_array( "nginx-custom", $apt_packages ) ) {
					if ( ! grep_string( '/etc/nginx/fastcgi_params', 'SCRIPT_FILENAME' ) ) {
						file_put_contents( '/etc/nginx/fastcgi_params', 'fastcgi_param \tSCRIPT_FILENAME \t$request_filename;\n' );
					}


					if ( ! file_exists( "/etc/nginx/common/wpfc.conf" ) ) {
						ee_file_search_replace( "/etc/nginx/nginx.conf", "# add_header", "add_header" );
						ee_file_search_replace( "/etc/nginx/nginx.conf", '"EasyEngine"', '"EasyEngine ' . EE_VERSION . '"' );
						$data = array();
						EE::debug( 'Writting the nginx configuration to file /etc/nginx/conf.d/blockips.conf' );
						EE\Utils\mustache_write_in_file( '/etc/nginx/conf.d/blockips.conf', 'blockips.conf', $data );

						EE::debug( 'Writting the nginx configuration to file /etc/nginx/conf.d/fastcgi.conf' );
						EE\Utils\mustache_write_in_file( '/etc/nginx/conf.d/fastcgi.conf', 'fastcgi.mustache', $data );

						$data             = array(
							"php"      => "9000",
							"debug"    => "9001",
							"hhvm"     => "8000",
							"php7"     => "9070",
							"debug7"   => "9170",
							"hhvmconf" => false,
						);
						$data['php7conf'] = EE_Apt_Get::is_installed( 'php7.0-fpm' ) ? true : false;
						EE::debug( 'Writting the nginx configuration to file /etc/nginx/conf.d/upstream.conf' );
						EE\Utils\mustache_write_in_file( '/etc/nginx/conf.d/upstream.conf', 'upstream.mustache', $data );

						ee_file_mkdir( "/etc/nginx/common" );
						$data            = array();
						$data["webroot"] = EE_WEBROOT;
						EE::debug( 'Writting the nginx configuration to file /etc/nginx/common/acl.conf' );
						EE\Utils\mustache_write_in_file( '/etc/nginx/common/acl.conf', 'acl.mustache', $data );

						EE::debug( 'Writting the nginx configuration to file /etc/nginx/common/locations.conf' );
						EE\Utils\mustache_write_in_file( '/etc/nginx/common/locations.conf', 'locations.mustache', $data );

						EE::debug( 'Writting the nginx configuration to file /etc/nginx/common/w3tc.conf' );
						EE\Utils\mustache_write_in_file( '/etc/nginx/common/w3tc.conf', 'w3tc.mustache', $data );

						EE::debug( 'Writting the nginx configuration to file /etc/nginx/common/wpfc.conf' );
						EE\Utils\mustache_write_in_file( '/etc/nginx/common/wpfc.conf', 'wpfc.mustache', $data );

						EE::debug( 'Writting the nginx configuration to file /etc/nginx/common/wpsubdir.conf' );
						EE\Utils\mustache_write_in_file( '/etc/nginx/common/wpsubdir.conf', 'wpsubdir.mustache', $data );
					}


					if ( ! file_exists( "/etc/nginx/common/php7.conf" ) ) {
						EE::debug( 'Writting the nginx configuration to file /etc/nginx/common/locations-php7.conf' );
						EE\Utils\mustache_write_in_file( '/etc/nginx/common/locations-php7.conf', 'locations-php7.mustache', $data );

						EE::debug( 'Writting the nginx configuration to file /etc/nginx/common/w3tc-php7.conf' );
						EE\Utils\mustache_write_in_file( '/etc/nginx/common/w3tc-php7.conf', 'w3tc-php7.mustache', $data );

						EE::debug( 'Writting the nginx configuration to file /etc/nginx/common/wpcommon-php7.conf' );
						EE\Utils\mustache_write_in_file( '/etc/nginx/common/wpcommon-php7.conf', 'wpcommon-php7.mustache', $data );

						EE::debug( 'Writting the nginx configuration to file /etc/nginx/common/wpfc-php7.conf' );
						EE\Utils\mustache_write_in_file( '/etc/nginx/common/wpfc-php7.conf', 'wpfc-php7.mustache', $data );

						EE::debug( 'Writting the nginx configuration to file /etc/nginx/common/wpsc-php7.conf' );
						EE\Utils\mustache_write_in_file( '/etc/nginx/common/wpsc-php7.conf', 'wpsc-php7.mustache', $data );

						EE::debug( 'Writting the nginx configuration to file /etc/nginx/common/redis-php7.conf' );
						EE\Utils\mustache_write_in_file( '/etc/nginx/common/redis-php7.conf', 'redis-php7.mustache', $data );

						EE::debug( 'Writting the nginx configuration to file /etc/nginx/common/php7.conf' );
						EE\Utils\mustache_write_in_file( '/etc/nginx/common/php7.conf', 'php7.mustache', $data );
					}

					// Nginx-Plus does not have nginx package structure like this
					// So creating directories
					if ( in_array( $apt_packages, "nginx-plus" ) || in_array( $apt_packages, "nginx" ) ) {
						EE::log( "Installing EasyEngine Configurations for NGINX" );
						ee_file_mkdir( "/etc/nginx/sites-available" );
						ee_file_mkdir( "/etc/nginx/sites-enabled" );
					}
					//22222 port settings
					EE::debug( 'Writting the nginx configuration to file /etc/nginx/sites-available/22222' );
					EE\Utils\mustache_write_in_file( '/etc/nginx/sites-available/22222', '22222.mustache', $data );

					$passwd = EE_Utils::random_string( 6 );
					EE::log($passwd);
					EE::exec_cmd( "printf \"easyengine:$(openssl passwd -crypt " . $passwd . "2> /dev/null)\" > /etc/nginx/htpasswd-ee 2>/dev/null" );


					ee_file_symlink( "/etc/nginx/sites-available/22222", "/etc/nginx/sites-enabled/22222" );

					//Create log and cert folder and softlinks

					EE::log( 'Creating directory' . EE_WEBROOT . '22222/logs' );
					ee_file_mkdir( EE_WEBROOT . "22222/logs" );

					EE::log( 'Creating directory' . EE_WEBROOT . '22222/cert' );
					ee_file_mkdir( EE_WEBROOT . "22222/cert" );

					ee_file_symlink( "/var/log/nginx/22222.access.log", EE_WEBROOT . "22222/logs/access.log" );
					ee_file_symlink( "/var/log/nginx/22222.error.log", EE_WEBROOT . "22222/logs/error.log" );

					EE::exec_cmd( "openssl genrsa -out " . EE_WEBROOT . "22222/cert/22222.key 2048" );
					EE::exec_cmd( "openssl req -new -batch -subj /commonName=127.0.0.1/ -key " . EE_WEBROOT . "22222/cert/22222.key -out " . EE_WEBROOT . "22222/cert/22222.csr" );
					ee_file_rename( EE_WEBROOT . "22222/cert/22222.key", EE_WEBROOT . "22222/cert/22222.key.org", true );
					EE::exec_cmd( "openssl rsa -in " . EE_WEBROOT . "22222/cert/22222.key.org -out " . EE_WEBROOT . "22222/cert/22222.key" );
					EE::exec_cmd( "openssl x509 -req -days 3652 -in " . EE_WEBROOT . "22222/cert/22222.csr -signkey " . EE_WEBROOT . "22222/cert/22222.key -out " . EE_WEBROOT . "22222/cert/22222.crt" );
					EE::log("adding git");
					EE_Git::add( array("/etc/nginx"), "Adding Nginx into Git" );
					EE::log("reloading nginx");
					EE_Service::reload_service( "nginx" );

					if ( in_array( "nginx-plus", $apt_packages ) || in_array( "nginx", $apt_packages ) ) {
						EE::exec_cmd( "sed -i -e 's/^user/#user/' -e '/^#user/a user www-data;' /etc/nginx/nginx.conf" );
						if ( ! EE::exec_cmd( "cat /etc/nginx/nginx.conf | grep -q '/etc/nginx/sites-enabled'" ) ) {
							EE::exec_cmd( "sed -i '/\/etc\/nginx\/conf\.d\/\*\.conf/a \    include\ \/etc\/nginx\/sites-enabled\/*;' /etc/nginx/nginx.conf" );
						}
						//EasyEngine config for NGINX plus
						$data            = array();
						$data['version'] = EE_VERSION;

						EE::debug( 'Writting for nginx plus configuration to file /etc/nginx/conf.d/ee-plus.conf' );
						EE\Utils\mustache_write_in_file( '/etc/nginx/conf.d/ee-plus.conf', 'ee-plus.mustache', $data );

						EE::success( "HTTP Auth User Name: easyengine" .
						             "\nHTTP Auth Password : " . $passwd );
						EE_Service::reload_service( "nginx" );

					} else {
						EE::success( "HTTP Auth User Name: easyengine" .
						             "\nHTTP Auth Password : " . $passwd );
						EE_Service::reload_service( "nginx" );
					}


				} else {
					EE_Service::restart_service( "nginx" );
				}
			}

			if (EE_Apt_Get::is_installed('redis-server')) {
				$data = array();

				if(file_exists("/etc/nginx/nginx.conf")&& !file_exists("/etc/nginx/common/redis.conf")){
					EE::debug( 'Writting the nginx configuration to file /etc/nginx/common/redis.conf' );
					EE\Utils\mustache_write_in_file('/etc/nginx/common/redis.conf', 'redis.mustache',$data);
				}
				if("trusty" == EE_OS::ee_platform_codename()|| "xenial" ==EE_OS::ee_platform_codename()){
					EE\Utils\mustache_write_in_file('/etc/nginx/common/redis-php7.conf', 'redis-php7.mustache',$data);

				}

				if(file_exists("/etc/nginx/conf.d/upstream.conf")){
					if(!grep_string("/etc/nginx/conf.d/upstream.conf", "redis")){
						$content = "upstream redis {\n".
					               "    server 127.0.0.1:6379;\n".
					               "    keepalive 10;\n}\n";
						ee_file_append_content( "/etc/nginx/conf.d/upstream.conf", $content );
					}
				}

				if(file_exists("/etc/nginx/nginx.conf")&&!file_exists("/etc/nginx/conf.d/redis.conf")){
					$content = "# Log format Settings".
						"log_format rt_cache_redis '\$remote_addr \$upstream_response_time \$srcache_fetch_status [\$time_local] '".
						"'\$http_host \"\$request\" \$status \$body_bytes_sent '".
						"'\"\$http_referer\" \"\$http_user_agent\"';";
					ee_file_append_content( "/etc/nginx/conf.d/redis.conf", $content );
				}
			}

			if (!empty(array_intersect(EE_Variables::get_php_packages('php7.0'), $apt_packages))) {
				EE::debug( 'Writting the nginx configuration to file PHP7.0' );
				EE\Utils\mustache_write_in_file('/etc/nginx/common/locations-php7.conf', 'locations-php7.mustache');
				EE\Utils\mustache_write_in_file('/etc/nginx/common/php7.conf', 'php7.mustache');
				EE\Utils\mustache_write_in_file('/etc/nginx/common/w3tc-php7.conf', 'w3tc-php7.mustache');
				EE\Utils\mustache_write_in_file('/etc/nginx/common/wpcommon-php7.conf', 'wpcommon-php7.mustache');
				EE\Utils\mustache_write_in_file('/etc/nginx/common/wpsc-php7.conf', 'wpsc-php7.mustache');
				EE\Utils\mustache_write_in_file('/etc/nginx/common/redis-php7.conf', 'redis-php7.mustache');

				if(file_exists("/etc/nginx/conf.d/upstream.conf")){
					if(!grep_string("/etc/nginx/conf.d/upstream.conf", "php7")){
						$content = "upstream php7 {\n".
						           "server 127.0.0.1:9070;\n}\n".
						           "upstream debug7 {\nserver 127.0.0.1:9170;\n}\n";
						ee_file_append_content( "/etc/nginx/conf.d/upstream.conf", $content );
					}
				}

				EEService::restart_service(self, 'nginx');
			}


			if ((EE_OS::ee_platform_distro() == 'debian' || EE_OS::ee_platform_codename() == 'precise') && (in_array($apt_packages, "EE_PHP"))) {
				ee_file_mkdir( "/var/log/php5/" );
				if ((EE_OS::ee_platform_distro() == 'debian' && EE_OS::ee_platform_codename() == 'wheezy')){
					EE::exec_cmd("pecl install xdebug");
					$content = "zend_extension=/usr/lib/php5/20131226/".
					           "xdebug.so\n";
					ee_file_append_content( "/etc/php5/mods-available/xdebug.ini", $content );
					ee_file_symlink("/etc/php5/mods-available/xdebug.ini","/etc/php5/fpm/conf.d/20-xedbug.ini");
				}
				$data = array();
				//todo: date time on php.ini
				EE::debug( 'Configuring php file /etc/php5/fpm/php.ini' );
				EE\Utils\mustache_write_in_file( '/etc/php5/fpm/php.ini', 'php-ini.mustache', $data );

				$data             = array(
					"pid"         => "/run/php5-fpm.pid",
					"error_log"   => "/var/log/php5/fpm.log",
					"include"     => "/etc/php5/fpm/pool.d/*.conf",
				);

				EE::debug( 'Configuring php file /etc/php5/fpm/php-fpm.conf' );
				EE\Utils\mustache_write_in_file( '/etc/php5/fpm/php-fpm.conf', 'php-fpm.mustache', $data );

				$data             = array(
					"listen"      => "127.0.0.1:9000",
				);
				EE::debug( 'Configuring php file /etc/php5/fpm/pool.d/www.conf' );
				EE\Utils\mustache_write_in_file( '/etc/php5/fpm/pool.d/www.conf', 'php-www.mustache', $data );

				$data             = array(
					"listen"           => "127.0.0.1:9001",
					"slowlog_path"     => "/var/log/php5/slow.log",
				);
				EE::debug( 'Configuring php file /etc/php5/fpm/pool.d/debug.conf' );
				EE\Utils\mustache_write_in_file( '/etc/php5/fpm/pool.d/debug.conf', 'php-debug.mustache', $data );
				ee_file_search_replace( "/etc/php5/mods-available/xdebug.ini", "zend_extension", ";zend_extension" );


//              todo: PHP and Debug pull configuration

				EE_Git::add("/etc/php5","Adding PHP in GIT");
				EE_Service::restart_service( "php5-fpm" );

			}

			if ((EE_OS::ee_platform_codename() == 'trusty' || EE_OS::ee_platform_codename() == 'xenial')
				&& (in_array($apt_packages, "EE_PHP5_6"))
			) {


				ee_file_mkdir( "/var/log/php/5.6/" );
				$data = array();
				//todo: date time on php.ini
				EE::debug( 'Configuring php file /etc/php/5.6/fpm/php.ini' );
				EE\Utils\mustache_write_in_file( '/etc/php/5.6/fpm/php.ini', 'php-ini.mustache', $data );

				$data             = array(
					"pid"         => "/run/php5.6-fpm.pid",
					"error_log"   => "/var/log/php/5.6/fpm.log",
					"include"     => "/etc/php/5.6/fpm/pool.d/*.conf",
				);

				EE::debug( 'Configuring php file /etc/php/5.6/fpm/php-fpm.conf' );
				EE\Utils\mustache_write_in_file( '/etc/php/5.6/fpm/php-fpm.conf', 'php-fpm.mustache', $data );

				$data             = array(
					"listen"      => "127.0.0.1:9000",
				);
				EE::debug( 'Configuring php file /etc/php/5.6/fpm/pool.d/www.conf' );
				EE\Utils\mustache_write_in_file( '/etc/php/5.6/fpm/pool.d/www.conf', 'php-www.mustache', $data );

				$data             = array(
					"listen"           => "127.0.0.1:9001",
					"slowlog_path"     => "/var/log/php/5.6/slow.log",
				);
				EE::debug( 'Configuring php file /etc/php/5.6/fpm/pool.d/debug.conf' );
				EE\Utils\mustache_write_in_file( '/etc/php/5.6/fpm/pool.d/debug.conf', 'php-debug.mustache', $data );
				ee_file_search_replace( "/etc/php/5.6/mods-available/xdebug.ini", "zend_extension", ";zend_extension" );

				//todo: PHP and Debug pull configuration

				EE_Git::add("/etc/php","Adding PHP in GIT");
				EE_Service::restart_service( "php5.6-fpm" );
			}


			if ((EE_OS::ee_platform_codename() == 'jessie')	&& (in_array($apt_packages, "EE_PHP7_0"))) {


				ee_file_mkdir( "/var/log/php/7.0/" );
				$data = array();
				//todo: date time on php.ini
				EE::debug( 'Configuring php file /etc/php/7.0/fpm/php.ini' );
				EE\Utils\mustache_write_in_file( '/etc/php/7.0/fpm/php.ini', 'php-ini.mustache', $data );

				$data             = array(
					"pid"         => "/run/php7.0-fpm.pid",
					"error_log"   => "/var/log/php/7.0/fpm.log",
					"include"     => "/etc/php/7.0/fpm/pool.d/*.conf",
				);

				EE::debug( 'Configuring php file /etc/php/7.0/fpm/php-fpm.conf' );
				EE\Utils\mustache_write_in_file( '/etc/php/7.0/fpm/php-fpm.conf', 'php-fpm.mustache', $data );

				$data             = array(
					"listen"      => "127.0.0.1:9000",
				);
				EE::debug( 'Configuring php file /etc/php/7.0/fpm/pool.d/www.conf' );
				EE\Utils\mustache_write_in_file( '/etc/php/7.0/fpm/pool.d/www.conf', 'php-www.mustache', $data );

				$data             = array(
					"listen"           => "127.0.0.1:9001",
					"slowlog_path"     => "/var/log/php/7.0/slow.log",
				);
				EE::debug( 'Configuring php file /etc/php/7.0/fpm/pool.d/debug.conf' );
				EE\Utils\mustache_write_in_file( '/etc/php/7.0/fpm/pool.d/debug.conf', 'php-debug.mustache', $data );
				ee_file_search_replace( "/etc/php/7.0/mods-available/xdebug.ini", "zend_extension", ";zend_extension" );

				//todo: PHP and Debug pull configuration

				EE_Git::add("/etc/php","Adding PHP in GIT");
				EE_Service::restart_service( "php7.0-fpm" );

			}

			if ((EE_OS::ee_platform_codename() == 'trusty' || EE_OS::ee_platform_codename() == 'xenial')
				&& (in_array($apt_packages, "EE_PHP7_0"))
			) {
				ee_file_mkdir( "/var/log/php/7.0/" );
				$data = array();
				//todo: date time on php.ini
				EE::debug( 'Configuring php file /etc/php/7.0/fpm/php.ini' );
				EE\Utils\mustache_write_in_file( '/etc/php/7.0/fpm/php.ini', 'php-ini.mustache', $data );

				$data             = array(
					"pid"         => "/run/php7.0-fpm.pid",
					"error_log"   => "/var/log/php/7.0/fpm.log",
					"include"     => "/etc/php/7.0/fpm/pool.d/*.conf",
				);

				EE::debug( 'Configuring php file /etc/php/7.0/fpm/php-fpm.conf' );
				EE\Utils\mustache_write_in_file( '/etc/php/7.0/fpm/php-fpm.conf', 'php-fpm.mustache', $data );

				$data             = array(
					"listen"      => "127.0.0.1:9000",
				);
				EE::debug( 'Configuring php file /etc/php/7.0/fpm/pool.d/www.conf' );
				EE\Utils\mustache_write_in_file( '/etc/php/7.0/fpm/pool.d/www.conf', 'php-www.mustache', $data );

				$data             = array(
					"listen"           => "127.0.0.1:9001",
					"slowlog_path"     => "/var/log/php/7.0/slow.log",
				);
				EE::debug( 'Configuring php file /etc/php/7.0/fpm/pool.d/debug.conf' );
				EE\Utils\mustache_write_in_file( '/etc/php/7.0/fpm/pool.d/debug.conf', 'php-debug.mustache', $data );
				ee_file_search_replace( "/etc/php/7.0/mods-available/xdebug.ini", "zend_extension", ";zend_extension" );

				//todo: PHP and Debug pull configuration

				EE_Git::add("/etc/php","Adding PHP in GIT");
				EE_Service::restart_service( "php7.0-fpm" );


			}

			if(in_array( $apt_packages, "mariadb-server" )){

				if (!is_file("/etc/mysql/my.cnf")){
					$config = "[mysqld]\nwait_timeout = 30\n".
                              "interactive_timeout=60\nperformance_schema = 0".
								"\nquery_cache_type = 1";
					ee_file_dump("/etc/mysql/my.cnf", $config);
				}else{
					EE::exec_cmd("sed -i \"/#max_connections/a wait_timeout = 30 \\ninteractive_timeout = 60 \\n" .
									"performance_schema = 0\\nquery_cache_type = 1 \" /etc/mysql/my.cnf");
				}
				$filesystem = new Filesystem();
				$filesystem->chmod("/usr/bin/mysqltuner",0775);
				EE_Git::add("/etc/mysql","Adding MySQL in GIT");
				EE_Service::restart_service( "mysql" );
			}


		}

		if (! empty($packages)){
			if (in_array('/usr/bin/wp', $packages)){
//				Log.debug(self, "Setting Privileges to /usr/bin/wp file ")
//EEFileUtils.chmod(self, "/usr/bin/wp", 0o775)
			}

			if (in_array('/tmp/pma.tar.gz', $packages)){
				//todo
			}

			if (in_array('/tmp/memcache.tar.gz', $packages)){
				//TODO:
			}

			if (in_array('/tmp/webgrind.tar.gz', $packages)){
				//TODO:
			}

			if (in_array('/tmp/anemometer.tar.gz', $packages)){
				//TODO:
			}

			if (in_array('/usr/bin/pt-query-advisor', $packages)){
				//TODO:
			}

			if (in_array('/tmp/vimbadmin.tar.gz', $packages)){
				//TODO:
			}

			if (in_array('/tmp/roundcube.tar.gz', $packages)){
				//TODO:
			}

			if (in_array('/tmp/pra.tar.gz', $packages)){
				//TODO:
			}

		}

	}

	/**
	 * Remove
	 *
	 * ## OPTIONS
	 *
	 *[--all]
	 *:all
	 * [--web]
	 * : To install web.
	 *
	 * [--admin]
	 *
	 * [--nginx]
	 * : To install nginx.
	 *
	 * [--php]
	 * : To install php.
	 *
	 * [--mysql]
	 * : To install MySQL.
	 *
	 *
	 * ## EXAMPLES
	 *
	 *      # Install Stack.
	 *      $ ee stack remove --nginx
	 *
	 */
	public function remove( $args, $assoc_args ) {

		list( $site_name ) = $args;
		$apt_packages = array();
		$packages = array();
		$stack = array();
		if(!empty($assoc_args['nginx'])){
			$stack['nginx']= true;
		}

		if (!empty($assoc_args['no_diplay_message'])){
			$disp_msg = false;
		}else{
			$disp_msg = true;
		}

		if( !empty( $assoc_args['pagespeed'] ) ) {
			EE::error( $site_name . 'Pagespeed support has been dropped since EasyEngine v3.6.0' );
			EE::error( $site_name . 'Please run command again without `--pagespeed`' );
			EE::error( $site_name . 'For more details, read - https://easyengine.io/blog/disabling-pagespeed/' );
		}

		if(!empty( $assoc_args['all'] )){
			$category['web'] = True;
			$category['admin'] = True;
		}

		if ($category['web'] == true){
			$stack['nginx']= true;
			$stack['php']= true;
			$stack['mysql']= true;
			$stack['wpcli']= true;
			$stack['postfix']= true;
		}
		if ($category['admin'] == true){
			$stack['nginx']= true;
			$stack['php']= true;
			$stack['mysql']= true;
			$stack['adminer']= true;
			$stack['phpmyadmin']= true;
			$category['utils']= true;
		}

		// if ($category['mail'] == true){
		// todo:
		// }

		if (!empty($stack['redis'])) {
			if (  EE_Apt_Get::is_installed( 'redis-server' ) ) {

				$apt_packages = array_merge( $apt_packages, EE_Variables::get_redis_packages() );
			} else {
				EE::success( "Redis not installed" );
			}
		}

		if ($stack['nginx']){
			if(EE_Apt_Get::is_installed('nginx-custom')){

				$apt_packages=array_merge($apt_packages,EE_Variables::get_nginx_packages());
			}else{
				EE::log("Nginx Stable not installed");
			}
		}
		if (!empty($stack['php'])){
			EE::debug("Setting apt_packages variable for PHP");
			if(EE_Apt_Get::is_installed('php5-fpm')||EE_Apt_Get::is_installed('php5.6-fpm')){
				if(EE_OS::ee_platform_codename() == 'trusty'||EE_OS::ee_platform_codename() == 'xenial'){
					$apt_packages = array_merge($apt_packages,EE_Variables::get_package_list('php5.6'),EE_Variables::get_package_list('phpextra'));
				}else{
					$apt_packages = array_merge($apt_packages,EE_Variables::get_php_packages( 'php' ));
				}
			}else{
				EE::success("PHP not installed");
			}
		}

		if ( EE_OS::ee_platform_distro() == 'debian' && !empty($stack['php'])){
			if (EE_OS::ee_platform_codename() == 'jessie'){
				EE::debug("Setting apt_packages variable for PHP 7.0");
				if(EE_Apt_Get::is_installed('php7.0-fpm')){
					$apt_packages = array_merge($apt_packages,EE_Variables::get_package_list('php7.0'));
					if(EE_Apt_Get::is_installed('php5-fpm')){
						$apt_packages = array_merge($apt_packages,EE_Variables::get_package_list('php'));
					}
				}else{
					EE::success("PHP 7.0 not installed");
				}
			}
		}


		if (!empty($stack['php'] && !EE_OS::ee_platform_codename() == 'debian')){
			if (EE_OS::ee_platform_codename() == 'trusty'||EE_OS::ee_platform_codename() == 'xenial'){
				EE::debug("Setting apt_packages variable for PHP 7.0");
				if(EE_Apt_Get::is_installed('php7.0-fpm')){
					$apt_packages = array_merge($apt_packages,EE_Variables::get_package_list('php7.0'));
					if(EE_Apt_Get::is_installed('php5.6-fpm')){
						$apt_packages = array_merge($apt_packages,EE_Variables::get_package_list('php5.6'),EE_Variables::get_package_list('phpextra'));
					}
				}else{
					EE::success("PHP 7.0 not installed");
				}
			}
		}

		if (!empty($stack['mysql'])){
			EE::debug("Setting apt_packages variable for MySQL");
			if (EE::exec_cmd("mysqladmin ping", $message = 'Looking for active mysql connection')){
				$apt_packages = array_merge($apt_packages,EE_Variables::get_mysql_packages());
				$packages = array_merge($packages, array("mysqltunner"));
			}else{
				EE::success("MySQL connection is not alive");
			}
		}


		if (!empty($stack['postfix'])){
			EE::debug("Setting apt_packages variable for Postfix");
			if(EE_Apt_Get::is_installed('postfix')){
				$apt_packages = array_merge($apt_packages,EE_Variables::get_package_list('postfix'));
			}else{
				EE::success("Postfix is not installed");
			}
		}

		if (!empty($stack['wpcli'])){
			EE::debug("Setting packages variable for WP-CLI");
			if (EE::exec_cmd("which wp", $message = 'Looking wp-cli preinstalled')){
				$packages = array_merge($packages, array("wpcli"));
			}
			else{
				EE::success("WP-CLI is not installed");
			}
		}

		if (!empty($stack['phpmyadmin'])){
			EE::debug("Setting packages variable for phpMyAdmin");
			$packages = array_merge($packages, array("phpmyadmin"));
		}

		if (!empty($stack['phpredisadmin'])){
			EE::debug("Setting packages variable for phpRedisAdmin");
			$packages = array_merge($packages, array("phpredisadmin"));
		}

		if (!empty($stack['adminer'])){
			EE::debug("Setting packages variable for Adminer");
			$packages = array_merge($packages, array("adminer"));
		}

		if (!empty($category['utils'])){
			EE::debug("Setting packages variable for utils");
			$packages = array_merge($packages, array("phpmemcacheadmin","opcache","rtcache-clean", "opcache-gui","ocp","webgrind","perconna-toolkit","anemometer"));
		}

		if(!empty($apt_packages)||!empty($packages)){;
			if(!empty($apt_packages)){
				EE_Apt_Get::remove($apt_packages);
			}
			if(!empty($packages)){
				EE::debug("Removing following: " .implode(' ',$packages));
				EE_Utils::remove($packages);
			}

			if ($disp_msg){
				EE::success("Successfully Removed Packages");
			}


		}
	}

		/**
		 * purge
		 *
		 * ## OPTIONS
		 *
		 *[--all]
		 *:all
		 * [--web]
		 * : To install web.
		 *
		 * [--admin]
		 *
		 * [--nginx]
		 * : To install nginx.
		 *
		 * [--php]
		 * : To install php.
		 *
		 * [--mysql]
		 * : To install MySQL.
		 *
		 *
		 * ## EXAMPLES
		 *
		 *      # Install Stack.
		 *      $ ee stack purge --nginx
		 */
	public function purge( $args, $assoc_args ) {

		list( $site_name ) = $args;
		$apt_packages = array();
		$packages = array();
		$stack = array();
		if(!empty($assoc_args['nginx'])){
			$stack['nginx']= true;
		}

		if (!empty($assoc_args['no_diplay_message'])){
			$disp_msg = false;
		}else{
			$disp_msg = true;
		}

		if( !empty( $assoc_args['pagespeed'] ) ) {
			EE::error( $site_name . 'Pagespeed support has been dropped since EasyEngine v3.6.0' );
			EE::error( $site_name . 'Please run command again without `--pagespeed`' );
			EE::error( $site_name . 'For more details, read - https://easyengine.io/blog/disabling-pagespeed/' );
		}

		if(!empty( $assoc_args['all'] )){
			$category['web'] = True;
			$category['admin'] = True;
		}

		if ($category['web'] == true){
			$stack['nginx']= true;
			$stack['php']= true;
			$stack['mysql']= true;
			$stack['wpcli']= true;
			$stack['postfix']= true;
		}
		if ($category['admin'] == true){
			$stack['nginx']= true;
			$stack['php']= true;
			$stack['mysql']= true;
			$stack['adminer']= true;
			$stack['phpmyadmin']= true;
			$category['utils']= true;
		}

		// if ($category['mail'] == true){
		// todo:
		// }

		if (!empty($stack['redis'])) {
			if (  EE_Apt_Get::is_installed( 'redis-server' ) ) {

				$apt_packages = array_merge( $apt_packages, EE_Variables::get_redis_packages() );
			} else {
				EE::success( "Redis not installed" );
			}
		}

		if ($stack['nginx']){
			if(EE_Apt_Get::is_installed('nginx-custom')){

				$apt_packages=array_merge($apt_packages,EE_Variables::get_nginx_packages());
			}else{
				EE::log("Nginx Stable not installed");
			}
		}
		if (!empty($stack['php'])){
			EE::debug("Setting apt_packages variable for PHP");
			if(EE_Apt_Get::is_installed('php5-fpm')||EE_Apt_Get::is_installed('php5.6-fpm')){
				if(EE_OS::ee_platform_codename() == 'trusty'||EE_OS::ee_platform_codename() == 'xenial'){
					$apt_packages = array_merge($apt_packages,EE_Variables::get_package_list('php5.6'),EE_Variables::get_package_list('phpextra'));
				}else{
					$apt_packages = array_merge($apt_packages,EE_Variables::get_php_packages( 'php' ));
				}
			}else{
				EE::success("PHP not installed");
			}
		}

		if ( EE_OS::ee_platform_distro() == 'debian' && !empty($stack['php'])){
			if (EE_OS::ee_platform_codename() == 'jessie'){
				EE::debug("Setting apt_packages variable for PHP 7.0");
				if(EE_Apt_Get::is_installed('php7.0-fpm')){
					$apt_packages = array_merge($apt_packages,EE_Variables::get_package_list('php7.0'));
					if(EE_Apt_Get::is_installed('php5-fpm')){
						$apt_packages = array_merge($apt_packages,EE_Variables::get_package_list('php'));
					}
				}else{
					EE::success("PHP 7.0 not installed");
				}
			}
		}


		if (!empty($stack['php'] && !EE_OS::ee_platform_codename() == 'debian')){
			if (EE_OS::ee_platform_codename() == 'trusty'||EE_OS::ee_platform_codename() == 'xenial'){
				EE::debug("Setting apt_packages variable for PHP 7.0");
				if(EE_Apt_Get::is_installed('php7.0-fpm')){
					$apt_packages = array_merge($apt_packages,EE_Variables::get_package_list('php7.0'));
					if(EE_Apt_Get::is_installed('php5.6-fpm')){
						$apt_packages = array_merge($apt_packages,EE_Variables::get_package_list('php5.6'),EE_Variables::get_package_list('phpextra'));
					}
				}else{
					EE::success("PHP 7.0 not installed");
				}
			}
		}

		if (!empty($stack['mysql'])){
			EE::debug("Setting apt_packages variable for MySQL");
			if (EE::exec_cmd("mysqladmin ping", $message = 'Looking for active mysql connection')){
				$apt_packages = array_merge($apt_packages,EE_Variables::get_mysql_packages());
				$packages = array_merge($packages, array("mysqltunner"));
			}else{
				EE::success("MySQL connection is not alive");
			}
		}


		if (!empty($stack['postfix'])){
			EE::debug("Setting apt_packages variable for Postfix");
			if(EE_Apt_Get::is_installed('postfix')){
				$apt_packages = array_merge($apt_packages,EE_Variables::get_package_list('postfix'));
			}else{
				EE::success("Postfix is not installed");
			}
		}

		if (!empty($stack['wpcli'])){
			EE::debug("Setting packages variable for WP-CLI");
			if (EE::exec_cmd("which wp", $message = 'Looking wp-cli preinstalled')){
				$packages = array_merge($packages, array("wpcli"));
			}
			else{
				EE::success("WP-CLI is not installed");
			}
		}

		if (!empty($stack['phpmyadmin'])){
			EE::debug("Setting packages variable for phpMyAdmin");
			$packages = array_merge($packages, array("phpmyadmin"));
		}

		if (!empty($stack['phpredisadmin'])){
			EE::debug("Setting packages variable for phpRedisAdmin");
			$packages = array_merge($packages, array("phpredisadmin"));
		}

		if (!empty($stack['adminer'])){
			EE::debug("Setting packages variable for Adminer");
			$packages = array_merge($packages, array("adminer"));
		}

		if (!empty($category['utils'])){
			EE::debug("Setting packages variable for utils");
			$packages = array_merge($packages, array("phpmemcacheadmin","opcache","rtcache-clean", "opcache-gui","ocp","webgrind","perconna-toolkit","anemometer"));
		}

		if(!empty($apt_packages)||!empty($packages)){;
			if(!empty($apt_packages)){
				EE_Apt_Get::remove($apt_packages,true);
			}
			if(!empty($packages)){
				EE::debug("Removing following: " .implode(' ',$packages));
				EE_Utils::remove($packages);
			}

			if ($disp_msg){
				EE::success("Successfully Removed Packages");
			}


		}
	}

}

EE::add_command( 'stack', 'Stack_Command' );
