<?php

use \EE\Utils;
use \EE\Dispatcher;

class Clean_Command extends EE_Command {


	public static function clean_redis() {
		//***This function clears Redis cache***
		if ( EE_Apt_Get::is_installed( 'redis-server' ) ) {
			EE::success( "Cleaning Redis Cache" );
			EE::exec_cmd( "redis-cli flushall" );
		}
	}

	public static function clean_memcache() {
		//***This function clears Redis cache***
		if ( EE_Apt_Get::is_installed( 'memcached' ) ) {
			EE::success( "Cleaning MemCache" );
			EE_Service::restart_service( "memcached" );
		}
	}

	public static function clean_fastcgi() {
		//***This function clears Fastcgi cache***
		if ( is_dir( "/var/run/nginx-cache" ) ) {
			EE::success( "Cleaning NGINX FastCGI cache" );
			EE::exec_cmd( "rm -rf /var/run/nginx-cache/*" );
		} else {
			EE::warning( "Unable to clean FastCGI cache" );
		}
	}

	public static function clean_opcache() {
		//todo: This function clears opcache
	}

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
	 *
	 * ## EXAMPLES
	 *
	 *      # Clear cache.
	 *      $ ee clean --all
	 *      $ ee clean --redis
	 *
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

