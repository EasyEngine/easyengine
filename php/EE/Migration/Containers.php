<?php

namespace EE\Migration;

use EE\RevertableStepProcessor;
use function EE\Utils\default_launch;
use EE;

/**
 * Migrates existing containers to new image
 */
class Containers {

	/** @var RevertableStepProcessor $rsp Keeps track of migration state. Reverts on error */
	private static $rsp;

	/**
	 * Main entry point for the script. Migrates all containers
	 *
	 * @throws \Exception
	 */
	public static function start_container_migration() {
		EE\Utils\delem_log( 'Starting container migration' );

		self::$rsp = new RevertableStepProcessor();
		self::docker_image_migration();
		// @todo: add doc blocks.
		// @todo: update database after image migration.
//		self::migrate_site_containers();
		if ( ! self::$rsp->execute() ) {
			throw new \Exception( 'Unable to migrate sites to newer version' );
		}

		EE\Utils\delem_log( 'Container migration completed' );
	}

	/**
	 * Migrate all docker images with new version.
	 */
	private static function docker_image_migration() {

		$img_versions     = EE\Utils\get_image_versions();
		$current_versions = self::get_current_docker_images_versions();

		$changed_images = [];
		foreach ( $img_versions as $img => $version ) {
			if ( $current_versions[ $img ] !== $version ) {
				$changed_images[] = $img;
				self::pull_or_error( $img, $version );
			}
		}

		if( empty( $changed_images) ) {
			return;
		}

		self::migrate_global_containers( $changed_images );

	}

	/**
	 * Helper method to ensure exception is thrown if image isn't pulled
	 *
	 * @param $image   docker image to pull
	 * @param $version version of image to pull
	 *
	 * @throws \Exception
	 */
	private static function pull_or_error( $image, $version ) {
		if ( ! \EE::exec( "docker pull $image:$version" ) ) {
			throw new \Exception( "Unable to pull $image. Please check logs for more details." );
		}
	}

	/**
	 * Get current docker images versions.
	 *
	 * @return array
	 * @throws \Exception
	 */
	private static function get_current_docker_images_versions() {
		$images = EE::db()
			->table( 'options' )
			->where( 'key', 'like', 'easyengine/%' )
			->all();

		$images = array_map( function ( $image ) {
			return [ $image['key'] => $image['value'] ];
		}, $images );

		return array_merge( ...$images );
	}

	/**
	 * Migrates all containers of existing sites
	 */
	private static function migrate_site_containers() {
		$db       = new \EE_DB();
		$sites       = $db->select( 'site_url', 'site_fs_path', 'site_type', 'cache_nginx_browser', 'site_ssl', 'db_host' );
		$site_docker = new \Site_Docker();

		foreach ( $sites as $site ) {

			$data   = [];
			$data[] = $site['site_type'];
			$data[] = $site['cache_nginx_browser'];
			$data[] = $site['site_ssl'];
			$data[] = $site['db_host'];

			$docker_compose_contents    = $site_docker->generate_docker_compose_yml( $data );
			$docker_compose_path        = $site['site_fs_path'] . '/docker-compose.yml';
			$docker_compose_backup_path = $site['site_fs_path'] . '/docker-compose.yml.bak';

			self::$rsp->add_step(
				"upgrade-${site['site_url']}-copy-compose-file",
				'EE\Migration\Containers::site_copy_compose_file_up',
				null,
				[ $site, $docker_compose_path, $docker_compose_backup_path ]
			);

			self::$rsp->add_step(
				"upgrade-${site['site_url']}-containers",
				'EE\Migration\Containers::site_containers_up',
				'EE\Migration\Containers::site_containers_down',
				[ $site, $docker_compose_backup_path, $docker_compose_path, $docker_compose_contents ],
				[ $site, $docker_compose_backup_path, $docker_compose_path ]
			);
		}
	}

	/**
	 * Migrates global containers. These are container which are not created per site (i.e. ee-cron-scheduler)
	 */
	private static function migrate_global_containers( $changes_images ) {

		self::$rsp->add_step(
			'backup-global-docker-compose-file',
			'EE\Migration\Containers::backup_global_compose_file',
			'EE\Migration\Containers::revert_global_containers',
			null,
			null
		);

		self::$rsp->add_step(
			'stop-global-containers',
			'EE\Migration\Containers::down_global_containers',
			null,
			[ $changes_images ],
			null
		);

		self::$rsp->add_step(
			'generate-global-docker-compose-file',
			'EE\Service\Utils\generate_global_docker_compose_yml',
			null,
			[ new \Symfony\Component\Filesystem\Filesystem() ],
			null
		);

		// Upgrade nginx-proxy container
		$existing_nginx_proxy_image = EE::launch( sprintf( 'docker inspect --format=\'{{.Config.Image}}\' %1$s', EE_PROXY_TYPE ), false, true );
		if ( in_array( 'easyengine/nginx-proxy', $changes_images, true ) && 0 === $existing_nginx_proxy_image->return_code ) {
			self::$rsp->add_step(
				'upgrade-nginxproxy-container',
				'EE\Migration\Containers::nginxproxy_container_up',
				'EE\Migration\Containers::nginxproxy_container_down',
				null,
				null
			);
		}

		// Upgrade global-db container
		$existing_db_image = EE::launch( 'docker inspect --format=\'{{.Config.Image}}\' ' . GLOBAL_DB_CONTAINER, false, true );
		if ( in_array( 'easyengine/mariadb', $changes_images, true ) && 0 === $existing_db_image->return_code ) {
			self::$rsp->add_step(
				'upgrade-global-db-container',
				'EE\Migration\Containers::global_db_container_up',
				'EE\Migration\Containers::global_db_container_down',
				null,
				null
			);
		}

		// Upgrade cron container
		$existing_cron_image = EE::launch( 'docker inspect --format=\'{{.Config.Image}}\' ' . EE_CRON_SCHEDULER, false, true );
		if ( in_array( 'easyengine/cron', $changes_images, true ) && 0 === $existing_cron_image->return_code ) {
			self::$rsp->add_step(
				'upgrade-cron-container',
				'EE\Migration\Containers::cron_container_up',
				'EE\Migration\Containers::cron_container_down',
				null,
				null
			);
		}

		// Upgrade redis container
		$existing_redis_image = EE::launch( 'docker inspect --format=\'{{.Config.Image}}\' ' . GLOBAL_REDIS_CONTAINER, false, true );
		if ( in_array( 'easyengine/cron', $changes_images, true ) && 0 === $existing_redis_image->return_code ) {
			self::$rsp->add_step(
				'upgrade-global-redis-container',
				'EE\Migration\Containers::global_redis_container_up',
				'EE\Migration\Containers::global_redis_container_down',
				null,
				null
			);
		}
	}

	public static function down_global_containers( $changed_images ) {

		chdir( EE_ROOT_DIR . '/services' );

		if ( in_array( 'easyengine/nginx-proxy', $changed_images, true ) ) {
			if ( ! EE::exec( 'docker-compose stop global-nginx-proxy && docker-compose rm -f global-nginx-proxy' ) ) {
				throw new \Exception( 'Unable to stop ' . EE_PROXY_TYPE . ' container' );
			}
		}

		if ( in_array( 'easyengine/mariadb', $changed_images, true ) ) {
			if ( ! EE::exec( 'docker-compose stop global-db && docker-compose rm -f global-db' ) ) {
				throw new \Exception( 'Unable to stop ' . GLOBAL_DB_CONTAINER . 'container' );
			}
		}

		if ( in_array( 'easyengine/redis', $changed_images, true ) ) {
			if ( ! EE::exec( 'docker-compose stop global-redis && docker-compose rm -f global-redis' ) ) {
				throw new \Exception( 'Unable to stop ' . GLOBAL_REDIS_CONTAINER . 'container' );
			}
		}

		if ( in_array( 'easyengine/cron', $changed_images, true ) ) {
			if ( ! EE::exec( 'docker-compose stop cron-scheduler && docker-compose rm -f cron-scheduler' ) ) {
				throw new \Exception( 'Unable to stop ' . GLOBAL_REDIS_CONTAINER . 'container' );
			}
		}

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
		};

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

		self::revert_global_containers();
	}

	public static function global_db_container_up() {
		chdir( EE_ROOT_DIR . '/services' );

		if( ! EE::exec( 'docker-compose up -d global-db' ) ) {
			throw new \Exception( sprintf( 'Unable to restart %1$s container', GLOBAL_DB_CONTAINER ) );
		};
	}

	public static function global_db_container_down() {
		chdir( EE_ROOT_DIR . '/services' );

		if( ! EE::exec( 'docker-compose stop global-db && docker-compose rm -f global-db' ) ) {
			throw new \Exception( sprintf( 'Unable to restart %1$s container', GLOBAL_DB_CONTAINER ) );
		};

		self::revert_global_containers();
	}

	public static function global_redis_container_up() {
		chdir( EE_ROOT_DIR . '/services' );

		if( ! EE::exec( 'docker-compose up -d global-redis' ) ) {
			throw new \Exception( sprintf( 'Unable to restart %1$s container', GLOBAL_REDIS_CONTAINER ) );
		};
	}

	public static function global_redis_container_down() {
		chdir( EE_ROOT_DIR . '/services' );

		if( ! EE::exec( 'docker-compose stop global-redis && docker-compose rm -f global-redis' ) ) {
			throw new \Exception( sprintf( 'Unable to restart %1$s container', GLOBAL_REDIS_CONTAINER ) );
		};
		self::revert_global_containers();
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
		};
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
		};
		self::revert_global_containers();
	}

	/**
	 * Copies updated docker-compose file site root
	 *
	 * @param $site Name of site
	 * @param $docker_compose_path Path where docker-compose.yml is to be copied
	 * @param $docker_compose_backup_path Path old docker-compose.yml is to be copied for backup
	 *
	 * @throws \Exception
	 */
	public static function site_copy_compose_file_up( $site, $docker_compose_path, $docker_compose_backup_path ) {
		if ( ! default_launch( "cp $docker_compose_path $docker_compose_backup_path" ) ) {
			throw new \Exception( "Unable to find docker-compose.yml in ${site['site_fs_path']} or couldn't create it's backup file. Ensure that EasyEngine has permission to create file there" );
		}
	}

	/**
	 * Upgrades site container of a site
	 *
	 * @param $site Name of site
	 * @param $docker_compose_backup_path Path of old docker-compose.yml file
	 * @param $docker_compose_path Path of updated docker-compose.yml file
	 * @param $docker_compose_contents Contents of updated docker-compose.yml file
	 *
	 * @throws \Exception
	 */
	public static function site_containers_up( $site, $docker_compose_backup_path, $docker_compose_path, $docker_compose_contents ) {
		file_put_contents( $docker_compose_path, $docker_compose_contents );
		$container_upgraded = default_launch( "cd ${site['site_fs_path']} && docker-compose up -d" );

		if ( ! $container_upgraded ) {
			throw new \Exception( "Unable to upgrade containers of site: ${site['site_url']}. Please check logs for more details." );
		}
	}

	/**
	 * Downgrades container of a site.
	 *
	 * @param $site Name of site
	 * @param $docker_compose_backup_path Path of old docker-compose.yml file
	 * @param $docker_compose_path Path of updated docker-compose.yml file
	 *
	 * @throws \Exception
	 */
	public static function site_containers_down( $site, $docker_compose_backup_path, $docker_compose_path ) {
		rename( $docker_compose_backup_path, $docker_compose_path );
		$container_downgraded = default_launch( "cd ${site['site_fs_path']} && docker-compose up -d" );

		if ( ! $container_downgraded ) {
			throw new \Exception( "Unable to downgrade containers of ${site['site_url']} site. Please check logs for more details." );
		}
	}
}
