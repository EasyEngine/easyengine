<?php

namespace EE\Migration;

use EE\RevertableStepProcessor;
use EE;
use Symfony\Component\Filesystem\Filesystem;

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

		$skip_download = [
			'easyengine/php5.6',
			'easyengine/php7.0',
			'easyengine/php7.2',
			'easyengine/php7.3',
			'easyengine/php7.4',
			'easyengine/php8.0',
			'easyengine/php8.1',
			'easyengine/php8.2',
			'easyengine/php8.3',
			'easyengine/newrelic-daemon',
		];

		foreach ( $img_versions as $img => $version ) {

			if ( array_key_exists( $img, $current_versions ) ) {
				if ( $current_versions[ $img ] !== $version ) {
					$updated_images[] = $img;
					if ( ! in_array( $img, $skip_download ) ) {
						self::pull_or_error( $img, $version );
					}
				}
			} else {
				if ( ! in_array( $img, $skip_download ) ) {
					self::pull_or_error( $img, $version );
				}
			}
		}

		if ( empty( $updated_images ) ) {
			return;
		}

		self::migrate_global_containers( $updated_images );
		self::migrate_site_containers( $updated_images );
		self::maybe_update_docker_compose();
		self::save_upgraded_image_versions( $current_versions, $img_versions, $updated_images );

		if ( ! self::$rsp->execute() ) {
			throw new \Exception( 'Unable to migrate sites to newer version' );
		}

		EE\Utils\delem_log( 'Container migration completed' );
	}

	/**
	 * Maybe update docker-compose at the end of migration.
	 * Need to update to latest docker-compose version for new template changes.
	 */
	public static function maybe_update_docker_compose() {

		self::$rsp->add_step(
			'update-compose',
			'EE\Migration\Containers::update_docker_compose',
			'EE\Migration\Containers::revert_docker_compose',
			null,
			null
		);
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

		self::$rsp->add_step(
			'prune-old-docker-images',
			'EE\Migration\Containers::image_cleanup',
			null,
			null,
			null
		);

	}

	/**
	 * Prune old and extra EE Docker images.
	 */
	public static function image_cleanup() {
		EE::exec( 'docker image prune -af --filter=label=org.label-schema.vendor="EasyEngine"' );
	}

	/**
	 * Update docker-compose to v2.26.1 if lower version is installed.
	 */
	public static function update_docker_compose() {

		$docker_compose_version     = EE::launch( 'docker-compose version --short' )->stdout;
		$docker_compose_path        = EE::launch( 'command -v docker-compose' )->stdout;
		$docker_compose_path        = trim( $docker_compose_path );
		$docker_compose_backup_path = EE_BACKUP_DIR . '/docker-compose.backup';
		$fs                         = new Filesystem();
		if ( ! $fs->exists( EE_BACKUP_DIR ) ) {
			$fs->mkdir( EE_BACKUP_DIR );
		}
		$fs->copy( $docker_compose_path, $docker_compose_backup_path );

		if ( version_compare( '2.26.1', $docker_compose_version, '>' ) ) {
			EE::exec( "curl -L https://github.com/docker/compose/releases/download/v2.26.1/docker-compose-$(uname -s)-$(uname -m) -o $docker_compose_path && chmod +x $docker_compose_path" );
		}
	}

	/**
	 * Revert docker-compose to previous version.
	 */
	public static function revert_docker_compose() {

		$docker_compose_path        = EE::launch( 'command -v docker-compose' )->stdout;
		$docker_compose_path        = trim( $docker_compose_path );
		$docker_compose_backup_path = EE_BACKUP_DIR . '/docker-compose.backup';
		$fs                         = new Filesystem();
		$fs->copy( $docker_compose_backup_path, $docker_compose_path );
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

		$dbImages = EE::db()
		              ->table( 'options' )
		              ->where( 'key', 'like', 'easyengine/%' )
		              ->all();

		$dbImages = array_column( $dbImages, 'value', 'key' );

		$dockerImages = EE::launch( 'docker ps --format "{{.Image}}" | grep "^easyengine/" | sort -u' )->stdout;
		$dockerImages = explode( "\n", trim( $dockerImages ) );

		$dockerImages = array_reduce( $dockerImages, function ( $result, $image ) {

			[ $imageName, $tag ] = explode( ':', $image, 2 ) + [ 1 => null ];
			$result[ $imageName ] = $tag;

			return $result;
		}, [] );

		$mergedImages = $dockerImages + $dbImages;
		$mergedImages = array_filter( $mergedImages );

		return $mergedImages;
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
			$global_service_name   = ltrim( $global_container_name, 'services_' );
			$global_service_name   = rtrim( $global_service_name, '_1' );
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

				/**
				 * Enable support containers.
				 */
				self::$rsp->add_step(
					sprintf( 'enable-support-containers-%s', $site['site_url'] ),
					'EE\Migration\SiteContainers::enable_support_containers',
					'EE\Migration\SiteContainers::disable_support_containers',
					[ $site['site_url'], $site['site_fs_path'] ],
					[ $site['site_url'], $site['site_fs_path'] ]
				);

				self::$rsp->add_step(
					"disable-${site['site_url']}-containers",
					'EE\Migration\SiteContainers::disable_default_containers',
					'EE\Migration\SiteContainers::enable_default_containers',
					[ $site ],
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
					'EE\Migration\SiteContainers::enable_default_containers',
					'EE\Migration\SiteContainers::enable_default_containers',
					[ $site, $ee_site_object ],
					[ $site, $ee_site_object ]
				);

				/**
				 * Disable support containers.
				 */
				self::$rsp->add_step(
					sprintf( 'disable-support-containers-%s', $site['site_url'] ),
					'EE\Migration\SiteContainers::disable_support_containers',
					'EE\Migration\SiteContainers::enable_support_containers',
					[ $site['site_url'], $site['site_fs_path'] ],
					[ $site['site_url'], $site['site_fs_path'] ]
				);
			}
		}
	}

}
