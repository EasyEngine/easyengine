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
			if ( 'easyengine/php5.6' === $img ) {
				continue;
			}
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
	 * Save updated image version in database.
	 *
	 * @param $current_versions array of current image versions.
	 * @param $new_versions     array of new image version.
	 * @param $updated_images   array of updated images.
	 */
	public static function save_upgraded_image_versions( $current_versions, $new_versions, $updated_images ) {

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
	public static function pull_or_error( $image, $version ) {
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
	public static function get_current_docker_images_versions() {
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

		$updated_global_images = GlobalContainers::get_updated_global_images( $updated_images );
		if ( empty( $updated_global_images ) ) {
			return;
		}

		$global_compose_file_path        = EE_ROOT_DIR . '/services/docker-compose.yml';
		$global_compose_file_backup_path = EE_BACKUP_DIR . '/services/docker-compose.yml.backup';

		self::$rsp->add_step(
			'backup-global-docker-compose-file',
			'EE\Migration\SiteContainers::backup_restore',
			'EE\Migration\GlobalContainers::revert_global_containers',
			[ $global_compose_file_path, $global_compose_file_backup_path ],
			[ $global_compose_file_backup_path, $global_compose_file_path, $updated_global_images ]
		);

		self::$rsp->add_step(
			'stop-global-containers',
			'EE\Migration\GlobalContainers::down_global_containers',
			null,
			[ $updated_global_images ],
			null
		);

		self::$rsp->add_step(
			'generate-global-docker-compose-file',
			'EE\Service\Utils\generate_global_docker_compose_yml',
			null,
			[ new \Symfony\Component\Filesystem\Filesystem() ],
			null
		);

		$all_global_images = GlobalContainers::get_all_global_images_with_service_name();
		foreach ( $updated_global_images as $image_name ) {
			$global_container_name = $all_global_images[ $image_name ];
			$global_service_name   = ltrim( $global_container_name, 'ee-' );
			self::$rsp->add_step(
				"upgrade-$global_container_name-container",
				"EE\Migration\GlobalContainers::global_service_up",
				"EE\Migration\GlobalContainers::global_service_down",
				[  $global_service_name ],
				[ $global_service_name ]
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

		foreach ( $sites as $site ) {

			$docker_yml        = $site['site_fs_path'] . '/docker-compose.yml';
			$docker_yml_backup = EE_BACKUP_DIR . '/' . $site['site_url'] . '/docker-compose.yml.backup';

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
				'EE\Migration\SiteContainers::backup_restore',
				'EE\Migration\SiteContainers::backup_restore',
				[ $docker_yml, $docker_yml_backup ],
				[ $docker_yml_backup, $docker_yml ]
			);

			self::$rsp->add_step(
				"generate-${site['site_url']}-docker-compose",
				'EE\Migration\SiteContainers::generate_site_docker_compose_file',
				null,
				[ $site, $ee_site_object ],
				null
			);

			if ( $site['site_enabled'] ) {
				self::$rsp->add_step(
					"upgrade-${site['site_url']}-containers",
					'EE\Migration\SiteContainers::enable_site',
					'EE\Migration\SiteContainers::enable_site',
					[ $site, $ee_site_object ],
					[ $site, $ee_site_object ]
				);
			}
		}
	}

}
