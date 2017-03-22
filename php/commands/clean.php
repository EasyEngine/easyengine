<?php
/**
 * Clear Cache
 *
 * ## OPTIONS
 *
 *
 * [--all]
 * : clear cache of all type
 *
 * [--fastcgi]
 * : Clear fastcgi cache
 *
 * [--memcache]
 * : Clear memcache
 *
 * [--redis]
 * : Clear redis cache
 *
 * [--opcache]
 * : Clear opcache cache
 *
 *
 * ## EXAMPLES
 *
 *      # Clear cache.
 *      $ ee clean --all
 *      $ ee clean --redis
 *
 * @package easyengine
 */

use \EE\Utils;
use \EE\Dispatcher;

/**
 * Class Clean_Command clear cache.
 */
class Clean_Command extends EE_Command {

	/**
	 * Clear Redis cache
	 */
	public static function clean_redis() {
		// This function clears Redis cache.
		if ( EE_Apt_Get::is_installed( 'redis-server' ) ) {
			EE::success( 'Cleaning Redis Cache' );
			EE::exec_cmd( 'redis-cli flushall' );
		} else {
		    EE::error( 'Redis not installed' );
		}
	}

	/**
	 * Clear Memcache
	 */
	public static function clean_memcache() {
		// This function clears Redis cache.
		if ( EE_Apt_Get::is_installed( 'memcached' ) ) {
			EE::success( 'Cleaning MemCache' );
			EE_Service::restart_service( 'memcached' );
		}
	}

	/**
	 * Clear FastCGI
	 */
	public static function clean_fastcgi() {
		// This function clears Fastcgi cache.
		if ( is_dir( '/var/run/nginx-cache' ) ) {
			EE::success( 'Cleaning NGINX FastCGI cache' );
			EE::exec_cmd( 'rm -rf /var/run/nginx-cache/*' );
		} else {
			EE::warning( 'Unable to clean FastCGI cache' );
		}
	}

	/**
	 * Clear opcache
	 */
	public static function clean_opcache() {
		// This function clears opcache.
		try {
			EE::success( 'Cleaning Opcache' );
			$fd = fopen( 'https://127.0.0.1:22222/cache/opcache/opgui.php?page=reset' );
			fread( $fd, filesize( 'https://127.0.0.1:22222/cache/opcache/opgui.php?page=reset' ) );
		} catch (Exception $e ) {
			EE::debug( 'Unable hit url, https://127.0.0.1:22222/cache/opcache/opgui.php?page=reset, please check you have admin tools installed' );
			EE::debug( 'please check you have admin tools installed, or install them with `ee stack install --admin`' );
			EE::error( 'Unable to clean opcache' );
		}
	}

	/**
	 * Invoke proper call based on request to clear cache.
	 *
	 * @param array $args argument values.
	 * @param array $assoc_args type of param for cache clear.
	 */
	public function __invoke( $args, $assoc_args ) {
		if ( ! empty( $assoc_args['all'] ) ) {
			$this->clean_fastcgi();
			$this->clean_memcache();
			$this->clean_redis();
		} elseif ( ! empty( $assoc_args['fastcgi'] ) ) {
			$this->clean_fastcgi();
		} elseif ( ! empty( $assoc_args['memcache'] ) ) {
			$this->clean_memcache();
		} elseif ( ! empty( $assoc_args['redis'] ) ) {
			$this->clean_redis();
		}

	}


}

EE::add_command( 'clean', 'Clean_Command' );

