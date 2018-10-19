<?php

namespace EE\Migration;

use EE\RevertableStepProcessor;
use EE;

/**
 * Upgrade existing containers to new docker-image
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

		$img_versions     = EE\Utils\get_image_versions();
		$current_versions = self::get_current_docker_images_versions();
		$updated_images   = [];

		foreach ( $img_versions as $img => $version ) {
			if ( $current_versions[ $img ] !== $version ) {
				$updated_images[] = $img;
				self::pull_or_error( $img, $version );
			}
		}

		if ( empty( $updated_images ) ) {
			return;
		}

		self::migrate_global_containers( $updated_images );
		self::migrate_site_containers( $updated_images );
		self::save_upgraded_image_versions( $current_versions, $img_versions, $updated_images );

		if ( ! self::$rsp->execute() ) {
			throw new \Exception( 'Unable to migrate sites to newer version' );
		}

		EE\Utils\delem_log( 'Container migration completed' );
	}

	/**
	 * Migrate all docker images with new version.
	 */
	private static function migrate_all_docker_images() {


	}

	/**
	 * Save updated image version in database.
	 *
	 * @param $current_versions array of current image versions.
	 * @param $new_versions     array of new image version.
	 * @param $updated_images   array of updated images.
	 */
	private static function save_upgraded_image_versions( $current_versions, $new_versions, $updated_images ) {

		self::$rsp->add_step(
			'save-image-verions-in-database',
			'EE\Migration\Containers::create_database_entry',
			'EE\Migration\Containers::revert_database_entry',
			[ $new_versions, $updated_images ],
			[ $current_versions, $updated_images ]

		);

	}

	/**
	 * Update database entry of images
	 *
	 * @param $new_versions   array of new image versions.
	 * @param $updated_images array of updated images.
	 *
	 * @throws \Exception
	 */
	public static function create_database_entry( $new_versions, $updated_images ) {
		foreach ( $updated_images as $image ) {
			EE\Model\Option::update( [ [ 'key', $image ] ], [ 'value' => $new_versions[ $image ] ] );
		}
	}

	/**
	 * Revert database entry in case of exception.
	 *
	 * @param $old_version    array of old image versions.
	 * @param $updated_images array of updated images.
	 *
	 * @throws \Exception
	 */
	public static function revert_database_entry( $old_version, $updated_images ) {
		foreach ( $updated_images as $image ) {
			EE\Model\Option::update( [ [ 'key', $image ] ], [ 'value' => $old_version[ $image ] ] );
		}
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
	 * Migrates global containers. These are container which are not created per site (i.e. ee-cron-scheduler).
	 *
	 * @param $updated_images array of updated images.
	 */
	private static function migrate_global_containers( $updated_images ) {

		if ( ! GlobalContainers::is_global_container_image_changed( $updated_images ) ) {
			return;
		}

		$global_compose_file_path        = EE_ROOT_DIR . '/services/docker-compose.yml';
		$global_compose_file_backup_path = EE_ROOT_DIR . '/services/docker-compose.yml.backup';

		self::$rsp->add_step(
			'backup-global-docker-compose-file',
			'EE\Migration\GlobalContainers::backup_global_compose_file',
			'EE\Migration\GlobalContainers::revert_global_containers',
			[ $global_compose_file_path, $global_compose_file_backup_path ],
			[ $global_compose_file_backup_path, $global_compose_file_path ]
		);

		self::$rsp->add_step(
			'stop-global-containers',
			'EE\Migration\GlobalContainers::down_global_containers',
			null,
			[ $updated_images ],
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
		if ( in_array( 'easyengine/nginx-proxy', $updated_images, true ) && 0 === $existing_nginx_proxy_image->return_code ) {
			self::$rsp->add_step(
				'upgrade-nginxproxy-container',
				'EE\Migration\GlobalContainers::nginxproxy_container_up',
				'EE\Migration\GlobalContainers::nginxproxy_container_down',
				null,
				null
			);
		}

		// Upgrade global-db container
		$existing_db_image = EE::launch( 'docker inspect --format=\'{{.Config.Image}}\' ' . GLOBAL_DB_CONTAINER, false, true );
		if ( in_array( 'easyengine/mariadb', $updated_images, true ) && 0 === $existing_db_image->return_code ) {
			self::$rsp->add_step(
				'upgrade-global-db-container',
				'EE\Migration\GlobalContainers::global_db_container_up',
				'EE\Migration\GlobalContainers::global_db_container_down',
				null,
				null
			);
		}

		// Upgrade cron container
		$existing_cron_image = EE::launch( 'docker inspect --format=\'{{.Config.Image}}\' ' . EE_CRON_SCHEDULER, false, true );
		if ( in_array( 'easyengine/cron', $updated_images, true ) && 0 === $existing_cron_image->return_code ) {
			self::$rsp->add_step(
				'upgrade-cron-container',
				'EE\Migration\GlobalContainers::cron_container_up',
				'EE\Migration\GlobalContainers::cron_container_down',
				null,
				null
			);
		}

		// Upgrade redis container
		$existing_redis_image = EE::launch( 'docker inspect --format=\'{{.Config.Image}}\' ' . GLOBAL_REDIS_CONTAINER, false, true );
		if ( in_array( 'easyengine/cron', $updated_images, true ) && 0 === $existing_redis_image->return_code ) {
			self::$rsp->add_step(
				'upgrade-global-redis-container',
				'EE\Migration\GlobalContainers::global_redis_container_up',
				'EE\Migration\GlobalContainers::global_redis_container_down',
				null,
				null
			);
		}
	}

	/**
	 *  Migrate site specific container.
	 *
	 * @param $updated_images array of updated images
	 *
	 * @throws \Exception
	 */
	public static function migrate_site_containers( $updated_images ) {

		$db    = new \EE_DB();
		$sites = ( $db->table( 'sites' )->all() );

		foreach ( $sites as $key => $site ) {

			$docker_yml        = $site['site_fs_path'] . '/docker-compose.yml';
			$docker_yml_backup = $site['site_fs_path'] . '/docker-compose.yml.backup';

			if ( ! SiteContainers::is_site_service_image_changed( $updated_images, $site ) ) {
				continue;
			}

			$ee_site_object = SiteContainers::get_site_object( $site['site_type'] );

			if ( $site['site_enabled'] ) {
				self::$rsp->add_step(
					"disable-${site['site_url']}-containers",
					'EE\Migration\SiteContainers::disable_site',
					'EE\Migration\SiteContainers::enable_site',
					[ $site, $ee_site_object ],
					[ $site, $ee_site_object ]
				);
			}

			self::$rsp->add_step(
				"take-${site['site_url']}-docker-compose-backup",
				'EE\Migration\SiteContainers::backup_site_docker_compose_file',
				'EE\Migration\SiteContainers::revert_site_docker_compose_file',
				[ $docker_yml, $docker_yml_backup ],
				[ $docker_yml_backup, $docker_yml ]
			);

			self::$rsp->add_step(
				"generate-${site['site_url']}-docker-compose",
				'EE\Migration\SiteContainers::generate_site_docker_compose_file',
				null,
				[ $site ],
				null
			);

			if ( $site['site_enabled'] ) {
				self::$rsp->add_step(
					"upgrade-${site['site_url']}-containers",
					'EE\Migration\SiteContainers::enable_site',
					null,
					[ $site, $ee_site_object ],
					null
				);
			}
		}
	}

}
