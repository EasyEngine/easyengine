<?php

class EE_Variables {

	/**
	 * Intialization of core variables.
	 */

	/**
	 * ee site list file name.
	 *
	 * @return string
	 */
	public static function get_ee_repo_file_path() {
		$ee_repo_file = "ee-repo.list";
		//		$ee_repo_file_path = "/etc/apt/sources.list.d/" . self::$ee_repo_file;
		// TODO: Test repo url.
		$ee_repo_file_path = EE_ROOT . '/' . $ee_repo_file;

		return $ee_repo_file_path;
	}

	/**
	 * EasyEngine version
	 *
	 * @return string
	 */
	public static function get_ee_version() {
		$ee_version = "3.7.4";

		return $ee_version;
	}

	/**
	 * Easyengine site database.
	 *
	 * @return string
	 */
	public static function get_ee_db_file() {
		$ee_db_path = EE_DB_FILE;

		return $ee_db_path;
	}

	public static function get_ee_mysql_host() {

		$ee_mysql_host = get_mysql_config( 'client', 'host' );
		if ( empty( $ee_mysql_host ) ) {
			$ee_mysql_host = 'localhost';
		}

		return $ee_mysql_host;
	}

	public static function get_ee_wp_cli_version() {

		$ee_wp_cli_version_check = EE::exec_cmd_output( "wp --version | awk '{print $2}' | cut -d'-' -f1 | tr -d '\n'" );
		if ( empty( $ee_wp_cli_version_check ) ) {
			$ee_wp_cli_version = EE_WP_CLI;
		} else {
			$ee_wp_cli_version = $ee_wp_cli_version_check;
		}

		return $ee_wp_cli_version;
	}

	public static function get_ee_webroot() {
		$ee_webroot = '/var/www/';

		return $ee_webroot;
	}

	public static function get_ee_php_user() {
		$ee_php_user = 'www-data';

		return $ee_php_user;
	}

	/**
	 * Nginx version.
	 *
	 * @return string
	 */
	public static function get_nginx_version() {

		$nginx_version_check = EE::exec_cmd_output( "nginx -v 2>&1 | cut -d':' -f2 | cut -d' ' -f2 |  cut -d'/' -f2 | tr -d '\n'" );

		if ( empty( $nginx_version_check ) ) {
			$nginx_version = EE_WP_CLI;
		} else {
			$nginx_version = $nginx_version_check;
		}

		return $nginx_version;
	}

	/**
	 * WP_CLI path.
	 *
	 * @return string
	 */
	public static function get_ee_wp_cli_path() {

		$ee_wpcli_path = EE::exec_cmd_output( 'which wp' );
		if ( empty( $ee_wpcli_path ) ) {
			$ee_wpcli_path = '/usr/bin/wp';
		}

		return $ee_wpcli_path;
	}

	/**
	 * Get php version.
	 *
	 * @return string
	 */
	public static function get_php_version() {
		$ee_platform_codename = EE_OS::ee_platform_codename();
		if ( 'trusty' == $ee_platform_codename || 'xenial' == $ee_platform_codename ) {
			$php = 'php5.6';
		} else {
			$php = 'php';
		}
		$php_version_cmd = $php . " -v 2>/dev/null | head -n1 | cut -d' ' -f2 | cut -d'+' -f1 | tr -d '\n'";
		$php_version     = EE::exec_cmd_output( $php_version_cmd );

		return $php_version;
	}

	/**
	 * Get list of packages need for on package.
	 *
	 * @param string $package package name i.e php
	 */
	public static function get_package_list( $package ) {
		if ( strpos( $package, 'php' ) !== false ) {
			self::get_php_packages( 'php' );
		}
	}

	/**
	 * Get MySQL repo name.
	 *
	 * @return array|string
	 */
	public static function get_mysql_repo() {
		$ee_platform_distro   = EE_OS::ee_platform_distro();
		$ee_platform_codename = EE_OS::ee_platform_codename();
		$ee_php_repo          = '';
		if ( 'ubuntu' === $ee_platform_distro ) {
			$ee_mysql_repo = "deb http://sfo1.mirrors.digitalocean.com/mariadb/repo/10.1/ubuntu" . $ee_platform_codename . "main";

		} else if ( 'debian' === $ee_platform_distro ) {
			$ee_mysql_repo = "deb http://sfo1.mirrors.digitalocean.com/mariadb/repo/10.1/debian" . $ee_platform_codename . "main";
		}

		return $ee_mysql_repo;
	}

	/**
	 * Get php repo name.
	 *
	 * @return array|string
	 */
	public static function get_php_repo() {
		$ee_platform_distro   = EE_OS::ee_platform_distro();
		$ee_platform_codename = EE_OS::ee_platform_codename();
		$ee_php_repo          = '';
		if ( 'ubuntu' === $ee_platform_distro ) {
			if ( 'precise' === $ee_platform_codename ) {
				$ee_php_repo = array( "ppa:ondrej/php5-5.6" );
			} else if ( 'trusty' === $ee_platform_codename && 'xenial' === $ee_platform_codename ) {
				$ee_php_repo = "ppa:ondrej/php";
			}
		} else if ( 'debian' === $ee_platform_distro ) {
			if ( 'wheezy' === $ee_platform_codename ) {
				$ee_php_repo = "deb http://packages.dotdeb.org {$ee_platform_codename}-php56 all";
			} else {
				$ee_php_repo = "deb http://packages.dotdeb.org {$ee_platform_codename} all";
			}
		}

		return $ee_php_repo;
	}

	/**
	 * Get packages of php.
	 *
	 * @param $package
	 *
	 * @return array|bool
	 */
	public static function get_php_packages( $package ) {
		$ee_platform_distro   = EE_OS::ee_platform_distro();
		$ee_platform_codename = EE_OS::ee_platform_codename();
		$ee_php               = array();
		$ee_php5_6            = array();
		$ee_php7_0            = array();
		$ee_php_extra         = array();
		if ( 'ubuntu' === $ee_platform_distro ) {
			if ( 'precise' === $ee_platform_codename ) {
				$ee_php = array(
					"php5-fpm",
					"php5-curl",
					"php5-gd",
					"php5-imap",
					"php5-mcrypt",
					"php5-common",
					"php5-readline",
					"php5-mysql",
					"php5-cli",
					"php5-memcache",
					"php5-imagick",
					"memcached",
					"graphviz",
					"php-pear"
				);
			} else if ( 'trusty' === $ee_platform_codename && 'xenial' === $ee_platform_codename ) {
				$ee_php5_6    = array(
					"php5.6-fpm",
					"php5.6-curl",
					"php5.6-gd",
					"php5.6-imap",
					"php5.6-mcrypt",
					"php5.6-readline",
					"php5.6-common",
					"php5.6-recode",
					"php5.6-mysql",
					"php5.6-cli",
					"php5.6-curl",
					"php5.6-mbstring",
					"php5.6-bcmath",
					"php5.6-mysql",
					"php5.6-opcache",
					"php5.6-zip",
					"php5.6-xml",
					"php5.6-soap"
				);
				$ee_php7_0    = array(
					"php7.0-fpm",
					"php7.0-curl",
					"php7.0-gd",
					"php7.0-imap",
					"php7.0-mcrypt",
					"php7.0-readline",
					"php7.0-common",
					"php7.0-recode",
					"php7.0-cli",
					"php7.0-mbstring",
					"php7.0-bcmath",
					"php7.0-mysql",
					"php7.0-opcache",
					"php7.0-zip",
					"php7.0-xml",
					"php7.0-soap"
				);
				$ee_php_extra = array(
					"php-memcached",
					"php-imagick",
					"php-memcache",
					"memcached",
					"graphviz",
					"php-pear",
					"php-xdebug",
					"php-msgpack",
					"php-redis"
				);
			}
		} else if ( 'debian' === $ee_platform_distro ) {
			$ee_php = array(
				"php5-fpm",
				"php5-curl",
				"php5-gd",
				"php5-imap",
				"php5-mcrypt",
				"php5-common",
				"php5-readline",
				"php5-mysqlnd",
				"php5-cli",
				"php5-memcache",
				"php5-imagick",
				"memcached",
				"graphviz",
				"php-pear"
			);

			$ee_php7_0 = array(
				"php7.0-fpm",
				"php7.0-curl",
				"php7.0-gd",
				"php7.0-imap",
				"php7.0-mcrypt",
				"php7.0-common",
				"php7.0-readline",
				"php7.0-redis",
				"php7.0-mysql",
				"php7.0-cli",
				"php7.0-memcache",
				"php7.0-imagick",
				"php7.0-mbstring",
				"php7.0-recode",
				"php7.0-bcmath",
				"php7.0-opcache",
				"php7.0-zip",
				"php7.0-xml",
				"php7.0-soap",
				"php7.0-msgpack",
				"memcached",
				"graphviz",
				"php-pear",
				"php7.0-xdebug"
			);
		}
		if ( 'wheezy' == $ee_platform_codename ) {
			$ee_php[] = "php5-dev";
		}
		if ( 'precise' == $ee_platform_codename && 'jessie' == $ee_platform_codename ) {
			$ee_php[] = "php5-xdebug";
		}

		if ( 'php' === $package ) {
			$ee_php[] = 'php-sqlite3';

			return $ee_php;
		} else if ( 'php5.6' === $package ) {
			$ee_php5_6[] = 'php5.6-sqlite3';

			return $ee_php5_6;
		} else if ( 'php7.0' === $package ) {
			$ee_php7_0[] = 'php7.0-sqlite3';

			return $ee_php7_0;
		} else if ( 'phpextra' === $package ) {
			return $ee_php_extra;
		} else {
			return false;
		}
	}
}