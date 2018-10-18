<?php

namespace EE\Migration;

use EE;

/**
 * Upgrade existing global containers to new docker-image
 */
class GlobalContainers {

	/**
	 * @param $updated_images array of updated docker-images
	 *
	 * @return bool
	 */
	public static function is_global_container_image_changed( $updated_images ) {
		$global_images = [
			'easyengine/mariadb',
			'easyengine/mariadb',
			'easyengine/redis',
			'easyengine/cron',
		];

		$commom_images = array_intersect( $updated_images, $global_images );

		if ( ! empty( $commom_images ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Take backup of current global docker-compose.yml file.
	 *
	 * @throws \Exception
	 */
	public static function backup_global_compose_file() {
		$global_compose_file_path = EE_ROOT_DIR . '/services/docker-compose.yml';
		$global_compose_backup_file_path = EE_ROOT_DIR . '/services/docker-compose.yml.backup';

		if ( ! EE::exec( "cp $global_compose_file_path $global_compose_backup_file_path" ) ) {
			throw new \Exception( "Unable to find docker-compose.yml or couldn't create it's backup file. Ensure that EasyEngine has permission to create file there" );
		}
	}

	/**
	 * Revert to backed up docker-compose.yml file.
	 *
	 * @throws \Exception
	 */
	public static function revert_global_containers() {
		$global_compose_file_path = EE_ROOT_DIR . '/services/docker-compose.yml';
		$global_compose_backup_file_path = EE_ROOT_DIR . '/services/docker-compose.yml.backup';

		rename( $global_compose_backup_file_path, $global_compose_file_path );
		chdir( EE_ROOT_DIR . '/services' );
		$container_downgraded = EE::exec( 'docker-compose up -d' );

		if ( ! $container_downgraded ) {
			throw new \Exception( 'Unable to downgrade global containers. Please check logs for more details.' );
		}
	}

	/**
	 * @param $updated_image array of updated images.
	 *
	 * @throws \Exception
	 */
	public static function down_global_containers( $updated_image ) {

		chdir( EE_ROOT_DIR . '/services' );

		if ( in_array( 'easyengine/nginx-proxy', $updated_image, true ) ) {
			if ( ! EE::exec( 'docker-compose stop global-nginx-proxy && docker-compose rm -f global-nginx-proxy' ) ) {
				throw new \Exception( 'Unable to stop ' . EE_PROXY_TYPE . ' container' );
			}
		}

		if ( in_array( 'easyengine/mariadb', $updated_image, true ) ) {
			if ( ! EE::exec( 'docker-compose stop global-db && docker-compose rm -f global-db' ) ) {
				throw new \Exception( 'Unable to stop ' . GLOBAL_DB_CONTAINER . 'container' );
			}
		}

		if ( in_array( 'easyengine/redis', $updated_image, true ) ) {
			if ( ! EE::exec( 'docker-compose stop global-redis && docker-compose rm -f global-redis' ) ) {
				throw new \Exception( 'Unable to stop ' . GLOBAL_REDIS_CONTAINER . 'container' );
			}
		}

		if ( in_array( 'easyengine/cron', $updated_image, true ) ) {
			if ( ! EE::exec( 'docker-compose stop cron-scheduler && docker-compose rm -f cron-scheduler' ) ) {
				throw new \Exception( 'Unable to stop ' . GLOBAL_REDIS_CONTAINER . 'container' );
			}
		}
	}

	/**
	 * Upgrades nginx-proxy container
	 *
	 * @throws \Exception
	 */
	public static function nginxproxy_container_up() {
		$default_conf_path = EE_ROOT_DIR . '/services/nginx-proxy/conf.d/default.conf';
		$fs                = new \Symfony\Component\Filesystem\Filesystem();

		if ( $fs->exists( $default_conf_path ) ) {
			$fs->remove( $default_conf_path );
		}

		chdir( EE_ROOT_DIR . '/services' );
		if( ! EE::exec( 'docker-compose up -d global-nginx-proxy ' ) ) {
			throw new \Exception( sprintf( 'Unable to restart %1$s container', EE_PROXY_TYPE ) );
		}
	}

	/**
	 * Downgrades nginx-proxy container
	 *
	 * @param $existing_nginx_proxy_image Old nginx-proxy image name
	 *
	 * @throws \Exception
	 */
	public static function nginxproxy_container_down( $existing_nginx_proxy_image ) {
		chdir( EE_ROOT_DIR . '/services' );

		if ( ! EE::exec('docker-compose stop global-nginx-proxy && docker-compose rm -f global-nginx-proxy') ) {
			throw new \Exception( sprintf( 'Unable to stop %1$s container', EE_PROXY_TYPE ) );
		}

		$default_conf_path = EE_ROOT_DIR . '/services/nginx-proxy/conf.d/default.conf';
		$fs                = new \Symfony\Component\Filesystem\Filesystem();

		if ( $fs->exists( $default_conf_path ) ) {
			$fs->remove( $default_conf_path );
		}
	}

	/**
	 * Upgrade global db container.
	 *
	 * @throws \Exception
	 */
	public static function global_db_container_up() {
		chdir( EE_ROOT_DIR . '/services' );

		if( ! EE::exec( 'docker-compose up -d global-db' ) ) {
			throw new \Exception( sprintf( 'Unable to restart %1$s container', GLOBAL_DB_CONTAINER ) );
		}
	}

	/**
	 * Remove upgraded global db container.
	 *
	 * @throws \Exception
	 */
	public static function global_db_container_down() {
		chdir( EE_ROOT_DIR . '/services' );

		if( ! EE::exec( 'docker-compose stop global-db && docker-compose rm -f global-db' ) ) {
			throw new \Exception( sprintf( 'Unable to restart %1$s container', GLOBAL_DB_CONTAINER ) );
		}
	}

	/**
	 * Upgrades ee-cron-scheduler container
	 *
	 * @throws \Exception
	 */
	public static function cron_container_up() {
		chdir( EE_ROOT_DIR . '/services' );

		if( ! EE::exec( 'docker-compose up -d docker-compose' ) ) {
			throw new \Exception( sprintf( 'Unable to restart %1$s container', EE_CRON_SCHEDULER ) );
		}
	}

	/**
	 * Downgrades ee-cron-scheduler container
	 *
	 * @param $existing_cron_image Old nginx-proxy image name
	 *
	 * @throws \Exception
	 */
	public static function cron_container_down( $existing_cron_image ) {
		chdir( EE_ROOT_DIR . '/services' );

		if( ! EE::exec( 'docker-compose stop cron-scheduler && docker-compose rm -f cron-scheduler' ) ) {
			throw new \Exception( sprintf( 'Unable to restart %1$s container', EE_CRON_SCHEDULER ) );
		}
	}

	/**
	 * Upgrade global redis container.
	 *
	 * @throws \Exception
	 */
	public static function global_redis_container_up() {
		chdir( EE_ROOT_DIR . '/services' );

		if( ! EE::exec( 'docker-compose up -d global-redis' ) ) {
			throw new \Exception( sprintf( 'Unable to restart %1$s container', GLOBAL_REDIS_CONTAINER ) );
		}
	}

	/**
	 * Remove upgraded global redis container.
	 *
	 * @throws \Exception
	 */
	public static function global_redis_container_down() {
		chdir( EE_ROOT_DIR . '/services' );

		if( ! EE::exec( 'docker-compose stop global-redis && docker-compose rm -f global-redis' ) ) {
			throw new \Exception( sprintf( 'Unable to restart %1$s container', GLOBAL_REDIS_CONTAINER ) );
		}
	}
}
