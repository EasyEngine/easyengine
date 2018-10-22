<?php

namespace EE\Migration;

use EE;

/**
 * Upgrade existing global containers to new docker-image
 */
class GlobalContainers {

	/**
	 * Get global containers which has new image update.
	 *
	 * @param $updated_images array of updated docker-images
	 *
	 * @return array
	 */
	public static function get_updated_global_images( $updated_images ) {
		$global_images = [
			'easyengine/nginx-proxy',
			'easyengine/mariadb',
			'easyengine/redis',
			'easyengine/cron',
		];

		return array_intersect( $updated_images, $global_images );
	}

	/**
	 * Take backup of current global docker-compose.yml file.
	 *
	 * @param $source_path string path of global docker-compose.yml
	 * @param $dest_path   string path of backup file.
	 *
	 * @throws \Exception
	 */
	public static function backup_global_compose_file( $source_path, $dest_path ) {

		if ( ! EE::exec( "cp $source_path $dest_path" ) ) {
			throw new \Exception( "Unable to find docker-compose.yml or couldn't create it's backup file. Ensure that EasyEngine has permission to create file there" );
		}
	}

	/**
	 * * Restore  backed up docker-compose.yml file.
	 *
	 * @param $source_path string path of backup file.
	 * @param $dest_path   string path of global docker-compose.yml
	 *
	 * @throws \Exception
	 */
	public static function revert_global_containers( $source_path, $dest_path ) {

		if ( ! EE::exec( "mv $source_path $dest_path" ) ) {
			throw new \Exception( 'Unable to restore backup of docker-compose.yml' );
		}

		chdir( EE_ROOT_DIR . '/services' );

		if ( ! EE::exec( 'docker-compose up -d' ) ) {
			throw new \Exception( 'Unable to downgrade global containers. Please check logs for more details.' );
		}
	}

	/**
	 * Stop global container and remove them.
	 *
	 * @param $updated_images array of newly available images.
	 *
	 * @throws \Exception
	 */
	public static function down_global_containers( $updated_images ) {

		chdir( EE_ROOT_DIR . '/services' );
		$all_global_images = self::get_all_global_images_with_service_name();

		foreach ( $updated_images as $image_name ) {
			$global_container_name = $all_global_images[ $image_name ];
			$global_service_name   = ltrim( $global_container_name, 'ee-' );

			if ( false !== \EE_DOCKER::container_status( $global_container_name ) ) {
				if ( ! EE::exec( "docker-compose stop $global_service_name && docker-compose rm -f $global_service_name" ) ) {
					throw new \Exception( "Unable to stop $global_container_name container" );
				}
			}
		}
	}

	/**
	 * Upgrades nginx-proxy container
	 *
	 * @throws \Exception
	 */
	public static function global_nginx_proxy_up() {
		$default_conf_path = EE_ROOT_DIR . '/services/nginx-proxy/conf.d/default.conf';
		$fs                = new \Symfony\Component\Filesystem\Filesystem();

		if ( $fs->exists( $default_conf_path ) ) {
			$fs->remove( $default_conf_path );
		}

		chdir( EE_ROOT_DIR . '/services' );
		if ( ! EE::exec( 'docker-compose up -d global-nginx-proxy ' ) ) {
			throw new \Exception( sprintf( 'Unable to restart %1$s container', EE_PROXY_TYPE ) );
		}
	}

	/**
	 * Remove nginx-proxy container
	 *
	 * @throws \Exception
	 */
	public static function global_nginx_proxy_down() {
		chdir( EE_ROOT_DIR . '/services' );

		if ( ! EE::exec( 'docker-compose stop global-nginx-proxy && docker-compose rm -f global-nginx-proxy' ) ) {
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
	public static function global_db_up() {
		chdir( EE_ROOT_DIR . '/services' );

		if ( ! EE::exec( 'docker-compose up -d global-db' ) ) {
			throw new \Exception( sprintf( 'Unable to restart %1$s container', GLOBAL_DB_CONTAINER ) );
		}
	}

	/**
	 * Remove upgraded global db container.
	 *
	 * @throws \Exception
	 */
	public static function global_db_down() {
		chdir( EE_ROOT_DIR . '/services' );

		if ( ! EE::exec( 'docker-compose stop global-db && docker-compose rm -f global-db' ) ) {
			throw new \Exception( sprintf( 'Unable to restart %1$s container', GLOBAL_DB_CONTAINER ) );
		}
	}

	/**
	 * Remove ee-cron-scheduler container
	 *
	 * @throws \Exception
	 */
	public static function cron_scheduler_up() {
		return;
		chdir( EE_ROOT_DIR . '/services' );

		if ( ! EE::exec( 'docker-compose up -d docker-compose' ) ) {
			throw new \Exception( sprintf( 'Unable to restart %1$s container', EE_CRON_SCHEDULER ) );
		}
	}

	/**
	 * Remove ee-cron-scheduler container
	 *
	 * @throws \Exception
	 */
	public static function cron_scheduler_down() {
		chdir( EE_ROOT_DIR . '/services' );

		if ( ! EE::exec( 'docker-compose stop cron-scheduler && docker-compose rm -f cron-scheduler' ) ) {
			throw new \Exception( sprintf( 'Unable to restart %1$s container', EE_CRON_SCHEDULER ) );
		}
	}

	/**
	 * Upgrade global redis container.
	 *
	 * @throws \Exception
	 */
	public static function global_redis_up() {
		chdir( EE_ROOT_DIR . '/services' );

		if ( ! EE::exec( 'docker-compose up -d global-redis' ) ) {
			throw new \Exception( sprintf( 'Unable to restart %1$s container', GLOBAL_REDIS_CONTAINER ) );
		}
	}

	/**
	 * Remove upgraded global redis container.
	 *
	 * @throws \Exception
	 */
	public static function global_redis_down() {
		chdir( EE_ROOT_DIR . '/services' );

		if ( ! EE::exec( 'docker-compose stop global-redis && docker-compose rm -f global-redis' ) ) {
			throw new \Exception( sprintf( 'Unable to restart %1$s container', GLOBAL_REDIS_CONTAINER ) );
		}
	}

	/**
	 * Get all global images with it's service name.
	 *
	 * @return array
	 */
	public static function get_all_global_images() {
		return [
			'easyengine/nginx-proxy' => EE_PROXY_TYPE,
			'easyengine/mariadb'     => GLOBAL_DB_CONTAINER,
			'easyengine/redis'       => GLOBAL_REDIS_CONTAINER,
//			'easyengine/cron' => EE_CRON_SCHEDULER,

		];
	}
}
