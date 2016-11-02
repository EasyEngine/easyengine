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
	 * : To install MySQL
	 *
	 * [--redis]
	 * : To install Redis.
	 *
	 * [--web]
	 * : install web stack
	 *
	 * [--wpcli]
	 * :To install wp-cli
	 *
	 * [--utils]
	 * : To install Utilities tools
	 *
	 *
	 * ## EXAMPLES
	 *
	 *      # Install Stack.
	 *      $ ee stack install --nginx
	 *
	 */
	public function install( $args, $assoc_args ) {

		EE_Stack::install($assoc_args);

	}


	/**
	 * Remove
	 *
	 * ## OPTIONS
	 *
	 *[--all]
	 *: To remove all stack
	 *
	 * [--web]
	 * : To remove web.
	 *
	 * [--admin]
	 *
	 * [--nginx]
	 * : To remove nginx.
	 *
	 * [--php]
	 * : To remove php.
	 *
	 * [--mysql]
	 * : To remove MySQL
	 *
	 * [--redis]
	 * : To remove Redis.
	 *
	 * [--web]
	 * : remove web stack
	 *
	 * [--wpcli]
	 * :To remove wp-cli
	 *
	 * [--utils]
	 * : To remove Utilities tools
	 *
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
		$stack = self::validate_stack_option($assoc_args);

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
				EE::debug("Nginx Stable not installed");
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


		}
	}

		/**
		 * purge
		 *
		 * ## OPTIONS
		 *
		 * [--all]
		 * : To purge all stack
		 *
		 * [--web]
		 * : To purge web.
		 *
		 * [--admin]
		 * :To purge admin tool
		 *
		 * [--nginx]
		 * : To purge nginx.
		 *
		 * [--php]
		 * : To purge php.
		 *
		 * [--mysql]
		 * : To purge MySQL
		 *
		 * [--redis]
		 * : To purge Redis.
		 *
		 * [--wpcli]
		 * :To purge wp-cli
		 *
		 * [--utils]
		 * : To purge Utilities tools
		 *
		 *
		 * ## EXAMPLES
		 *
		 *      # Purge Stack.
		 *      $ ee stack purge --nginx
		 */
	public function purge( $args, $assoc_args ) {


		$apt_packages = array();
		$packages = array();
		$stack = self::validate_stack_option($assoc_args);


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
				EE::debug("Nginx Stable not installed");
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


		if (isset($stack['php']) && !empty($stack['php'] && !EE_OS::ee_platform_codename() == 'debian')){
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
		}
	}

	/**
	 * start
	 *
	 * ## OPTIONS
	 *
	 * [--all]
	 * : To purge all stack
	 *
	 * [--nginx]
	 * : To purge nginx.
	 *
	 * [--php]
	 * : To purge php.
	 *
	 * [--mysql]
	 * : To purge MySQL
	 *
	 * [--redis]
	 * : To purge Redis.
	 *
	 *
	 *
	 * ## EXAMPLES
	 *
	 *      # Start Stack.
	 *      $ ee stack start --nginx
	 */

	public function start($args, $assoc_args ){

		$services = EE_Stack::get_service_list($assoc_args);

		foreach ( $services as $service ) {
			EE::debug("Starting Services : ".$service);
			EE_Service::start_service($service);
		}


	}

	public function stop($args, $assoc_args ){
		//todo:
	}

	public function reload($args, $assoc_args ){
		//todo:
	}

	public function restart($args, $assoc_args ){
		//todo:
	}

	public function status($args, $assoc_args ){
		//todo:
	}

}

EE::add_command( 'stack', 'Stack_Command' );
