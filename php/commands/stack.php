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
	 * Create site.
	 *
	 * ## OPTIONS
	 *
	 *
	 * [--web]
	 * : To install web.
	 *
	 * [--nginx]
	 * : To install nginx.
	 *
	 * [--php]
	 * : To install nginx.
	 *
	 * ## EXAMPLES
	 *
	 *      # Create site.
	 *      $ ee site create example.com
	 *
	 */
	public function install( $args, $assoc_args ) {

		list( $site_name ) = $args;

		if( !empty( $assoc_args['pagespeed'] ) ) {
			EE_CLI::error( $site_name . 'Pagespeed support has been dropped since EasyEngine v3.6.0' );
			EE_CLI::error( $site_name . 'Please run command again without `--pagespeed`' );
			EE_CLI::error( $site_name . 'For more details, read - https://easyengine.io/blog/disabling-pagespeed/' );
		}

	if(!empty( $assoc_args['all'] )){
		$category['web'] = True;
		$category['admin'] = True;
	}
	$stack = array();
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

	if (!empty($stack['redis'])){
		if(!EE_Apt_Get::is_installed('redis-server')){
			$apt_packages = array_merge($apt_packages,EE_Variables::get_package_list('redis'));
	}
	}else{
			EE::success("Redis already installed");
		}
	}

	if (!empty($stack['nginx'])){
		EE::debug("Setting apt_packages variable for Nginx");
		if(!EE_Apt_Get::is_installed('nginx-custom')){
			if(!(EE_Apt_Get::is_installed('nginx-plus')||EE_Apt_Get::is_installed('nginx'))){
				$apt_packages = array_merge($apt_packages,EE_Variables::get_package_list('nginx'));
			}else{
					if(EE_Apt_Get::is_installed('nginx-plus')){
						EE::success("NGINX PLUS Detected ...");
						$apt[]="nginx-plus";
						$apt=array_merge($apt,EE_Variables::get_package_list('nginx'));
						self::post_pref($apt, $packages);
					}elseif(EE_Apt_Get::is_installed('nginx')){
						EE:success("EasyEngine detected a previously installed Nginx package. ".
						"It may or may not have required modules. ".
						"\nIf you need help, please create an issue at https://github.com/EasyEngine/easyengine/issues/ \n");
						$apt[]="nginx";
						$apt=array_merge($apt,EE_Variables::get_package_list('nginx'));
						self::post_pref($apt, $packages);
					}
			}
		}else{
			EE::debug("Nginx Stable already installed");
		}
	}
	if (!empty($stack['php'])){
		EE::debug("Setting apt_packages variable for PHP");
		if(!(EE_Apt_Get::is_installed('php5-fpm')||if(EE_Apt_Get::is_installed('php5.6-fpm')))){
			if(EE_OS::ee_platform_codename() == 'trusty'||EE_OS::ee_platform_codename() == 'xenial'){
				$apt_packages = array_merge($apt_packages,EE_Variables::get_package_list('php5.6'),EE_Variables::get_package_list('phpextra'));
			}else{
				$apt_packages = array_merge($apt_packages,EE_Variables::get_package_list('php'));
			}
		}else{
			EE:success("PHP already installed");
		}
	}

	if (!empty($stack['php'] && EE_OS::ee_platform_distro == 'debian')){
		if (EE_OS::ee_platform_codename == 'jessie'){
			EE:debug("Setting apt_packages variable for PHP 7.0");
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


	if (!empty($stack['php'] && !EE_OS::ee_platform_distro == 'debian')){
		if (EE_OS::ee_platform_codename == 'trusty'||EE_OS::ee_platform_codename == 'xenial'){
			EE:debug("Setting apt_packages variable for PHP 7.0");
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

	if (!empty($stack['mysql'] ){
		EE::debug("Setting apt_packages variable for MySQL");
		if (!EE::exec_cmd_output("mysqladmin ping", $message = 'Looking for active mysql connection', $exit_on_error = false);){
			$apt_packages = array_merge($apt_packages,EE_Variables::get_package_list('mysql'));
			$packages = array_merge($packages, array("mysqltunner");
		}else{
			EE::success("MySQL connection is already alive");
		}
	}


	if (!empty($stack['postfix'] ){
		EE::debug("Setting apt_packages variable for Postfix");
		if(!EE_Apt_Get::is_installed('postfix')){
			$apt_packages = array_merge($apt_packages,EE_Variables::get_package_list('postfix'));
		}else{
			EE::success("Postfix is already installed");
		}
	}

	if (!empty($stack['wpcli'] ){
		EE::debug("Setting packages variable for WP-CLI");
		if (!EE::exec_cmd_output("which wp", $message = 'Looking wp-cli preinstalled', $exit_on_error = false);){
			$packages = array_merge($packages, array("wpcli");
		}else{
			EE::success("WP-CLI is already installed");
		}
	}

	if (!empty($stack['phpmyadmin'] ){
		EE::debug("Setting packages variable for phpMyAdmin");
			$packages = array_merge($packages, array("phpmyadmin");
	}

	if (!empty($stack['phpredisadmin'] ){
		EE::debug("Setting packages variable for phpRedisAdmin");
			$packages = array_merge($packages, array("phpredisadmin");
	}

	if (!empty($stack['adminer'] ){
		EE::debug("Setting packages variable for Adminer");
			$packages = array_merge($packages, array("adminer");
	}

	if (!empty($category['utils'] ){
		EE::debug("Setting packages variable for utils");
			$packages = array_merge($packages, array("phpmemcacheadmin","opcache","rtcache-clean", "opcache-gui","ocp","webgrind","perconna-toolkit","anemometer");
	}



EE::add_command( 'stack', 'Stack_Command' );
