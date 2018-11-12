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

		$global_images           = self::get_all_global_images_with_service_name();
		$running_global_services = [];
		foreach ( $global_images as $image => $container_name ) {
			if ( 'running' === \EE_DOCKER::container_status( $container_name ) ) {
				$running_global_services[] = $image;
			}
		}

		return array_intersect( $running_global_services, $updated_images );
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
		EE::debug( 'Start backing up of global docker-compose.yml file' );
		if ( ! EE::exec( "cp $source_path $dest_path" ) ) {
			throw new \Exception( "Unable to find docker-compose.yml or couldn't create it's backup file. Ensure that EasyEngine has permission to create file there" );
		}
		EE::debug( 'Complete backing up of global docker-compose.yml file' );
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
		EE::debug( 'Start restoring global docker-compose.yml file from backup' );
		if ( ! EE::exec( "mv $source_path $dest_path" ) ) {
			throw new \Exception( 'Unable to restore backup of docker-compose.yml' );
		}

		chdir( EE_ROOT_DIR . '/services' );

		if ( ! EE::exec( 'docker-compose up -d' ) ) {
			throw new \Exception( 'Unable to downgrade global containers. Please check logs for more details.' );
		}
		EE::debug( 'Complete restoring global docker-compose.yml file from backup' );
	}

	/**
	 * Stop global container and remove them.
	 *
	 * @param $updated_images array of newly available images.
	 *
	 * @throws \Exception
	 */
	public static function down_global_containers( $updated_images ) {
		EE::debug( 'Start removing global containers' );
		chdir( EE_ROOT_DIR . '/services' );
		$all_global_images = self::get_all_global_images_with_service_name();

		foreach ( $updated_images as $image_name ) {
			$global_container_name = $all_global_images[ $image_name ];
			$global_service_name   = ltrim( $global_container_name, 'ee-' );
			EE::debug( "Removing  $global_container_name" );

			if ( false !== \EE_DOCKER::container_status( $global_container_name ) ) {
				if ( ! EE::exec( "docker-compose stop $global_service_name && docker-compose rm -f $global_service_name" ) ) {
					throw new \Exception( "Unable to stop $global_container_name container" );
				}
			}
		}
		EE::debug( 'Complete removing global containers' );
	}

	/**
	 * Upgrades nginx-proxy container
	 *
	 * @throws \Exception
	 */
	public static function global_nginx_proxy_up() {
		EE::debug( 'Start ' . EE_PROXY_TYPE . ' container up' );
		$default_conf_path = EE_ROOT_DIR . '/services/nginx-proxy/conf.d/default.conf';
		$fs                = new \Symfony\Component\Filesystem\Filesystem();

		if ( $fs->exists( $default_conf_path ) ) {
			$fs->remove( $default_conf_path );
		}

		chdir( EE_ROOT_DIR . '/services' );
		if ( ! EE::exec( 'docker-compose up -d global-nginx-proxy ' ) ) {
			throw new \Exception( sprintf( 'Unable to restart %1$s container', EE_PROXY_TYPE ) );
		}
		EE::debug( 'Complete ' . EE_PROXY_TYPE . ' container up' );;
	}

	/**
	 * Remove nginx-proxy container
	 *
	 * @throws \Exception
	 */
	public static function global_nginx_proxy_down() {
		EE::debug( 'Start ' . EE_PROXY_TYPE . ' container removing' );
		chdir( EE_ROOT_DIR . '/services' );

		if ( ! EE::exec( 'docker-compose stop global-nginx-proxy && docker-compose rm -f global-nginx-proxy' ) ) {
			throw new \Exception( sprintf( 'Unable to stop %1$s container', EE_PROXY_TYPE ) );
		}

		$default_conf_path = EE_ROOT_DIR . '/services/nginx-proxy/conf.d/default.conf';
		$fs                = new \Symfony\Component\Filesystem\Filesystem();

		if ( $fs->exists( $default_conf_path ) ) {
			$fs->remove( $default_conf_path );
		}
		EE::debug( 'Complete ' . EE_PROXY_TYPE . ' container removing' );
	}

	/**
	 * Upgrade global db container.
	 *
	 * @throws \Exception
	 */
	public static function global_db_up() {
		EE::debug( 'Start ' . GLOBAL_DB_CONTAINER . ' container up' );
		chdir( EE_ROOT_DIR . '/services' );

		if ( ! EE::exec( 'docker-compose up -d global-db' ) ) {
			throw new \Exception( sprintf( 'Unable to restart %1$s container', GLOBAL_DB_CONTAINER ) );
		}
		EE::debug( 'Complete' . GLOBAL_DB_CONTAINER . ' container up' );
	}

	/**
	 * Remove upgraded global db container.
	 *
	 * @throws \Exception
	 */
	public static function global_db_down() {
		EE::debug( 'Start ' . GLOBAL_DB_CONTAINER . ' container removing' );
		chdir( EE_ROOT_DIR . '/services' );

		if ( ! EE::exec( 'docker-compose stop global-db && docker-compose rm -f global-db' ) ) {
			throw new \Exception( sprintf( 'Unable to restart %1$s container', GLOBAL_DB_CONTAINER ) );
		}
		EE::debug( 'Complete ' . GLOBAL_DB_CONTAINER . ' container removing' );
	}

	/**
	 * Remove ee-cron-scheduler container
	 *
	 * @throws \Exception
	 */
	public static function cron_scheduler_up() {
		EE::debug( 'Start ' . EE_CRON_SCHEDULER . ' container up' );

		chdir( EE_ROOT_DIR . '/services' );

		if ( ! EE::exec( 'docker-compose up -d docker-compose' ) ) {
			throw new \Exception( sprintf( 'Unable to restart %1$s container', EE_CRON_SCHEDULER ) );
		}
		EE::debug( 'Complete ' . EE_CRON_SCHEDULER . ' container up' );
	}

	/**
	 * Remove ee-cron-scheduler container
	 *
	 * @throws \Exception
	 */
	public static function cron_scheduler_down() {
		EE::debug( 'Start ' . EE_CRON_SCHEDULER . ' container removing' );
		chdir( EE_ROOT_DIR . '/services' );

		if ( ! EE::exec( 'docker-compose stop cron-scheduler && docker-compose rm -f cron-scheduler' ) ) {
			throw new \Exception( sprintf( 'Unable to restart %1$s container', EE_CRON_SCHEDULER ) );
		}
		EE::debug( 'Complete ' . EE_CRON_SCHEDULER . ' container removing' );
	}

	/**
	 * Upgrade global redis container.
	 *
	 * @throws \Exception
	 */
	public static function global_redis_up() {
		EE::debug( 'Start ' . GLOBAL_REDIS_CONTAINER . ' container up' );
		chdir( EE_ROOT_DIR . '/services' );

		if ( ! EE::exec( 'docker-compose up -d global-redis' ) ) {
			throw new \Exception( sprintf( 'Unable to restart %1$s container', GLOBAL_REDIS_CONTAINER ) );
		}
		EE::debug( 'Complete ' . GLOBAL_REDIS_CONTAINER . ' container up' );
	}

	/**
	 * Remove upgraded global redis container.
	 *
	 * @throws \Exception
	 */
	public static function global_redis_down() {
		EE::debug( 'Start ' . GLOBAL_REDIS_CONTAINER . ' container removing' );
		chdir( EE_ROOT_DIR . '/services' );

		if ( ! EE::exec( 'docker-compose stop global-redis && docker-compose rm -f global-redis' ) ) {
			throw new \Exception( sprintf( 'Unable to restart %1$s container', GLOBAL_REDIS_CONTAINER ) );
		}
		EE::debug( 'Complete ' . GLOBAL_REDIS_CONTAINER . ' container removing' );
	}

	/**
	 * Get all global images with it's service name.
	 *
	 * @return array
	 */
	public static function get_all_global_images_with_service_name() {
		return [
			'easyengine/nginx-proxy' => EE_PROXY_TYPE,
			'easyengine/mariadb'     => GLOBAL_DB_CONTAINER,
			'easyengine/redis'       => GLOBAL_REDIS_CONTAINER,
			// 'easyengine/cron'        => EE_CRON_SCHEDULER, //TODO: Add it to global docker-compose.
		];
	}
}
