<?php

namespace EE\Migration;

use EE\RevertableStepProcessor;
use EE;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Upgrade existing containers to new docker-image
 */
class Containers {

	/** EasyEngine needs at least this docker-compose (see Runner::check_requirements()). */
	const DOCKER_COMPOSE_MIN_VERSION = '2.26.1';

	/** Installed when docker-compose is missing or older than the minimum. */
	const DOCKER_COMPOSE_INSTALL_VERSION = '2.27.0';

	/** @var RevertableStepProcessor $rsp Keeps track of migration state. Reverts on error */
	private static $rsp;

	/** @var bool Whether the last update_docker_compose() call replaced the binary. */
	private static $replaced_docker_compose = false;

	/** @var string[] Images (without tag) the last get_current_docker_images_versions() found running. */
	private static $running_images = [];

	/**
	 * Main entry point for the script. Migrates all containers
	 *
	 * @throws \Exception
	 */
	public static function start_container_migration() {
		EE\Utils\delem_log( 'Starting container migration' );

		self::$rsp = new RevertableStepProcessor( true );

		$img_versions     = EE\Utils\get_image_versions();
		$current_versions = self::get_current_docker_images_versions();
		$updated_images   = [];

		// An interrupted upgrade recreated some global services without recording their tags. Count those by their
		// recorded tags, so this run checks their readiness again and can roll them back to the pending snapshot.
		if ( GlobalContainers::has_pending_compose_backup( EE_BACKUP_DIR . '/services/docker-compose.yml.backup' ) ) {
			foreach ( array_keys( GlobalContainers::get_all_global_images_with_service_name() ) as $image ) {
				$recorded = EE\Model\Option::get( $image );
				if ( $recorded ) {
					$current_versions[ $image ] = $recorded;
				}
			}
		}

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
			'easyengine/php8.4',
			'easyengine/php8.5',
			'easyengine/php',
			'easyengine/newrelic-daemon',
		];

		foreach ( $img_versions as $img => $version ) {
			if ( array_key_exists( $img, $current_versions ) && $current_versions[ $img ] !== $version ) {
				$updated_images[] = $img;
			}
		}

		if ( empty( $updated_images ) ) {
			// Disabled sites an earlier upgrade skipped, or an interrupted one left behind, have no image in $updated_images.
			if ( self::get_disabled_sites_to_update( $img_versions ) ) {
				self::migrate_site_containers( [] );
				if ( ! self::$rsp->execute() ) {
					throw new \Exception( 'Unable to migrate sites to newer version' );
				}
			}
			// Every tag is recorded (e.g. a run interrupted just after saving them): a pending snapshot is done with.
			GlobalContainers::clear_pending_compose_backup( EE_BACKUP_DIR . '/services/docker-compose.yml.backup' );

			return;
		}

		// Stopped global services keep their old image and tag (see migrate_global_containers()), so their images stay
		// "updated" on every later run: when nothing running uses them and nothing else is left to do, stop here.
		$by_state = GlobalContainers::get_updated_global_images_by_state( $updated_images, $img_versions );
		$idle     = array_diff( $by_state['stopped'], self::$running_images );
		if ( ! array_diff( $updated_images, $idle ) && ! self::has_old_tags( EE_SERVICE_DIR . '/docker-compose.yml', $idle, $img_versions ) && ! self::get_disabled_sites_to_update( $img_versions ) ) {
			EE::debug( 'Only stopped global services have new images, and their compose files have them already.' );
			// Nothing running needs a gate (that would count as updated), so an interrupted run's snapshot is done with.
			GlobalContainers::clear_pending_compose_backup( EE_BACKUP_DIR . '/services/docker-compose.yml.backup' );

			return;
		}

		// An image no container runs and the DB doesn't track (e.g. mailhog, the amd64-only easyengine/php) has nothing
		// to upgrade: pulling it only fails on hosts it isn't built for. Nor has one only stopped services use, which keep
		// their old image. Compose pulls these when something first uses them. A stopped service on its new tag has it.
		foreach ( array_diff( $updated_images, $idle, $by_state['current'], $skip_download ) as $img ) {
			self::pull_or_error( $img, $img_versions[ $img ] );
		}

		// First: compose v1 can't recreate containers on Docker 25+, and on a rollback it is restored last.
		self::maybe_update_docker_compose();
		self::migrate_global_containers( $by_state );
		self::migrate_site_containers( $updated_images );
		self::save_upgraded_image_versions( $current_versions, $img_versions, array_values( array_diff( $updated_images, $by_state['stopped'] ) ) );

		if ( ! self::$rsp->execute() ) {
			throw new \Exception( 'Unable to migrate sites to newer version' );
		}

		EE\Utils\delem_log( 'Container migration completed' );
	}

	/**
	 * Maybe update docker-compose before any container is recreated, for the new templates and because v1 fails to
	 * recreate containers on current Docker.
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

		// The upgrade is committed: a later run takes a fresh snapshot of the global compose file.
		self::$rsp->add_step(
			'clear-pending-global-compose-backup',
			'EE\Migration\GlobalContainers::clear_pending_compose_backup',
			null,
			[ EE_BACKUP_DIR . '/services/docker-compose.yml.backup' ],
			null
		);

		self::$rsp->add_step(
			'prune-old-docker-images',
			'EE\Migration\Containers::docker_cleanup',
			null,
			null,
			null
		);

	}

	/**
	 * Prune old and extra EE Docker images.
	 */
	public static function docker_cleanup() {
		EE::exec( 'docker image prune -af --filter=label=org.label-schema.vendor="EasyEngine"' );

		// Not `docker network prune`: it also removes the networks of stopped containers, which then can't start, and the
		// global networks that disabled sites join as external networks on `ee site enable`.
		$keep     = [ defined( 'GLOBAL_FRONTEND_NETWORK' ) ? GLOBAL_FRONTEND_NETWORK : 'ee-global-frontend-network', defined( 'GLOBAL_BACKEND_NETWORK' ) ? GLOBAL_BACKEND_NETWORK : 'ee-global-backend-network' ];
		$networks = array_filter( explode( "\n", trim( EE::launch( 'docker network ls --filter=label=org.label-schema.vendor="EasyEngine" --format "{{.Name}}"' )->stdout ) ) );
		foreach ( array_diff( $networks, $keep ) as $network ) {
			$users = EE::launch( 'docker ps -aq --filter network=' . escapeshellarg( $network ) );
			if ( 0 === $users->return_code && '' === trim( $users->stdout ) ) {
				EE::exec( 'docker network rm ' . escapeshellarg( $network ) );
			}
		}
	}

	/**
	 * Install docker-compose DOCKER_COMPOSE_INSTALL_VERSION when it is missing or older than DOCKER_COMPOSE_MIN_VERSION.
	 * A newer one is never replaced. A failed download or check only warns and keeps the installed binary.
	 */
	public static function update_docker_compose() {

		// Only this call's replacement may be reverted, not one made earlier in the run (e.g. by a DB migration, which has no undo).
		self::$replaced_docker_compose = false;
		$installed                     = self::get_docker_compose_version();
		if ( '' !== $installed && version_compare( $installed, self::DOCKER_COMPOSE_MIN_VERSION, '>=' ) ) {
			EE::debug( "docker-compose $installed is recent enough, not replacing it." );

			return;
		}

		$version = self::DOCKER_COMPOSE_INSTALL_VERSION;
		$url     = self::get_docker_compose_download_url( $version, php_uname( 's' ), php_uname( 'm' ) );
		if ( '' === $url ) {
			EE::warning( sprintf( 'EasyEngine requires docker-compose %s or newer, and there is no docker-compose %s build for %s %s. Please install it manually.', self::DOCKER_COMPOSE_MIN_VERSION, $version, php_uname( 's' ), php_uname( 'm' ) ) );

			return;
		}

		$docker_compose_path        = trim( EE::launch( 'command -v docker-compose' )->stdout );
		$docker_compose_path        = '' === $docker_compose_path ? '/usr/local/bin/docker-compose' : $docker_compose_path;
		$docker_compose_backup_path = EE_BACKUP_DIR . '/docker-compose.backup';
		$docker_compose_new_path    = EE_BACKUP_DIR . '/docker-compose';

		$fs = new Filesystem();
		$fs->mkdir( EE_BACKUP_DIR );
		$fs->remove( $docker_compose_new_path );

		$failure = '';
		// --max-time and --retry-max-time: a stalled download must not hang the upgrade (curl retries timeouts too).
		if ( ! EE::exec( sprintf( 'curl -fsSL --retry 3 --retry-max-time 120 --connect-timeout 10 --max-time 300 -o %s %s', escapeshellarg( $docker_compose_new_path ), escapeshellarg( $url ) ) ) ) {
			$failure = "the download of $url failed";
		} else {
			$checksum = strtok( trim( EE::launch( sprintf( 'curl -fsSL --retry 3 --retry-max-time 60 --connect-timeout 10 --max-time 60 %s', escapeshellarg( "$url.sha256" ) ) )->stdout ), " \t" );
			$actual   = is_file( $docker_compose_new_path ) ? hash_file( 'sha256', $docker_compose_new_path ) : '';
			if ( ! preg_match( '/^[0-9a-f]{64}$/', (string) $checksum ) || ! hash_equals( (string) $checksum, (string) $actual ) ) {
				$failure = "the checksum of $url doesn't match";
			} else {
				$fs->chmod( $docker_compose_new_path, 0755 );
				$new_version = self::normalize_version( EE::launch( escapeshellarg( $docker_compose_new_path ) . ' version --short' )->stdout );
				if ( ! version_compare( $new_version, self::DOCKER_COMPOSE_MIN_VERSION, '>=' ) ) {
					$failure = 'the downloaded binary does not run';
				}
			}
		}

		if ( '' === $failure ) {
			// A backup from an earlier run must never be restored over this install.
			$fs->remove( $docker_compose_backup_path );
			if ( $fs->exists( $docker_compose_path ) ) {
				$fs->copy( $docker_compose_path, $docker_compose_backup_path, true );
			}
			if ( EE::exec( sprintf( 'mv %s %s', escapeshellarg( $docker_compose_new_path ), escapeshellarg( $docker_compose_path ) ) ) ) {
				self::$replaced_docker_compose = true;
				EE::debug( "Installed docker-compose $version at $docker_compose_path" );

				return;
			}
			$failure = "it could not be moved to $docker_compose_path";
		}

		$fs->remove( $docker_compose_new_path );
		EE::warning( sprintf( 'Could not install docker-compose %s (%s). EasyEngine requires docker-compose %s or newer, found %s.', $version, $failure, self::DOCKER_COMPOSE_MIN_VERSION, '' === $installed ? 'none' : $installed ) );
	}

	/**
	 * Revert docker-compose to the binary the last update_docker_compose() call replaced, if it did.
	 * An older backup from an earlier run is never restored.
	 */
	public static function revert_docker_compose() {

		if ( ! self::$replaced_docker_compose ) {
			return;
		}

		$docker_compose_path        = trim( EE::launch( 'command -v docker-compose' )->stdout );
		$docker_compose_backup_path = EE_BACKUP_DIR . '/docker-compose.backup';
		$fs                         = new Filesystem();
		if ( '' !== $docker_compose_path && $fs->exists( $docker_compose_backup_path ) ) {
			$fs->copy( $docker_compose_backup_path, $docker_compose_path, true );
		}
		self::$replaced_docker_compose = false;
	}

	/**
	 * @return string Installed docker-compose version as x.y.z, or '' if there is none.
	 */
	public static function get_docker_compose_version() {
		return self::normalize_version( EE::launch( 'docker-compose version --short' )->stdout );
	}

	/**
	 * Release asset URL of a docker-compose v2 build.
	 *
	 * @param string $version Version without `v`.
	 * @param string $os      `uname -s`, e.g. Linux or Darwin.
	 * @param string $arch    `uname -m`.
	 *
	 * @return string '' when there is no build for this platform.
	 */
	public static function get_docker_compose_download_url( $version, $os, $arch ) {
		$os     = strtolower( $os );
		$arches = [
			'x86_64'  => 'x86_64',
			'amd64'   => 'x86_64',
			'aarch64' => 'aarch64',
			'arm64'   => 'aarch64',
			'armv7l'  => 'armv7',
			'armv6l'  => 'armv6',
			'ppc64le' => 'ppc64le',
			's390x'   => 's390x',
			'riscv64' => 'riscv64',
		];
		$arch   = $arches[ strtolower( $arch ) ] ?? '';

		if ( '' === $arch || ! ( 'linux' === $os || ( 'darwin' === $os && in_array( $arch, [ 'x86_64', 'aarch64' ], true ) ) ) ) {
			return '';
		}

		return sprintf( 'https://github.com/docker/compose/releases/download/v%s/docker-compose-%s-%s', $version, $os, $arch );
	}

	/**
	 * @param string $output Output of `docker-compose version --short`, e.g. `2.27.0`, `v2.40.3` or `5.5.1-desktop.1`.
	 *
	 * @return string x.y.z, or ''.
	 */
	private static function normalize_version( $output ) {
		return preg_match( '/(\d+\.\d+\.\d+)/', (string) $output, $matches ) ? $matches[1] : '';
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

		$dockerImages = array_reduce( array_filter( $dockerImages ), function ( $result, $image ) {

			[ $imageName, $tag ] = explode( ':', $image, 2 ) + [ 1 => null ];
			$result[ $imageName ] = $tag;

			return $result;
		}, [] );

		self::$running_images = array_keys( $dockerImages );
		$mergedImages         = $dockerImages + $dbImages;
		$mergedImages = array_filter( $mergedImages );

		return $mergedImages;
	}

	/**
	 * Migrates global containers. These are container which are not created per site (i.e. ee-cron-scheduler).
	 *
	 * @param array $by_state Updated global images by state, from GlobalContainers::get_updated_global_images_by_state().
	 */
	private static function migrate_global_containers( $by_state ) {

		$global_compose_file_path = EE_ROOT_DIR . '/services/docker-compose.yml';

		// A stopped service keeps its container (Docker restarts it on boot) and the old image the cleanup then keeps, so
		// its old tag stays recorded; the regenerated compose file has the new one for `ee service enable`. A missing
		// service is created from that file later. Without a file, one is generated with the new tags when needed.
		$updated_global_images = $by_state['running'];
		if ( empty( $updated_global_images ) && ( ! is_file( $global_compose_file_path ) || empty( $by_state['stopped'] ) && empty( $by_state['missing'] ) ) ) {
			return;
		}

		$global_compose_file_backup_path = EE_BACKUP_DIR . '/services/docker-compose.yml.backup';
		$all_global_images               = GlobalContainers::get_all_global_images_with_service_name();
		GlobalContainers::reset_recreated();

		if ( in_array( 'easyengine/nginx-proxy', $updated_global_images, true ) ) {
			// Before anything is recreated: a rollback after the DB moved to a new image may not get its datadir back.
			self::$rsp->add_step(
				'check-running-proxy-config',
				'EE\Migration\GlobalContainers::check_running_proxy_config',
				null,
				[ $all_global_images['easyengine/nginx-proxy'] ],
				null
			);
		}

		self::$rsp->add_step(
			'backup-global-docker-compose-file',
			'EE\Migration\GlobalContainers::backup_compose_file',
			'EE\Migration\GlobalContainers::revert_global_containers',
			[ $global_compose_file_path, $global_compose_file_backup_path ],
			[ $global_compose_file_backup_path, $global_compose_file_path, $updated_global_images ]
		);

		// The services keep running until each is recreated in turn: no step stops them all at once.
		self::$rsp->add_step(
			'generate-global-docker-compose-file',
			'EE\Service\Utils\generate_global_docker_compose_yml',
			null,
			[ new \Symfony\Component\Filesystem\Filesystem() ],
			null
		);

		// No down steps: the backup step's down restores the old compose file and recreates these services from it.
		foreach ( GlobalContainers::sort_for_upgrade( $updated_global_images ) as $image_name ) {
			$global_container_name = $all_global_images[ $image_name ];
			self::$rsp->add_step(
				"upgrade-$global_container_name-container",
				'EE\Migration\GlobalContainers::upgrade_global_service',
				null,
				[ GlobalContainers::get_service_name( $global_container_name ), $global_container_name ],
				null
			);
		}
	}

	/**
	 * @param string $compose_file   A docker-compose.yml.
	 * @param array  $images         Images to check.
	 * @param array  $img_versions   This release's tags.
	 *
	 * @return bool Whether the file sets one of $images to a tag other than this release's.
	 */
	private static function has_old_tags( $compose_file, $images, $img_versions ) {
		foreach ( SiteContainers::get_compose_file_images( $compose_file ) as $image => $tag ) {
			if ( in_array( $image, $images, true ) && isset( $img_versions[ $image ] ) && $img_versions[ $image ] !== $tag ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param array $img_versions This release's tags.
	 *
	 * @return array Disabled sites whose docker-compose.yml sets an EasyEngine image to a tag other than this release's.
	 */
	private static function get_disabled_sites_to_update( $img_versions ) {
		$sites = ( new \EE_DB() )->table( 'sites' )->all();

		return array_values(
			array_filter(
				$sites,
				function ( $site ) use ( $img_versions ) {
					return ! $site['site_enabled'] && self::has_old_tags( $site['site_fs_path'] . '/docker-compose.yml', array_keys( $img_versions ), $img_versions );
				}
			)
		);
	}

	/**
	 *  Migrate site specific container.
	 *
	 * @param $updated_images array of updated images
	 *
	 * @throws \Exception
	 */
	public static function migrate_site_containers( $updated_images ) {

		$db           = new \EE_DB();
		$sites        = ( $db->table( 'sites' )->all() );
		$img_versions = EE\Utils\get_image_versions();

		foreach ( $sites as $site ) {

			$docker_yml        = $site['site_fs_path'] . '/docker-compose.yml';
			$docker_yml_backup = EE_BACKUP_DIR . '/' . $site['site_url'] . '/docker-compose.yml.backup';

			// A disabled site has no containers, only its compose file, which nothing else would move to the new images.
			// It is compared with this release's tags, not with this run's changed images: those can miss an image an
			// interrupted run already moved for the enabled sites, or an earlier upgrade recorded without updating it.
			if ( ! $site['site_enabled'] ) {
				if ( self::has_old_tags( $docker_yml, array_keys( $img_versions ), $img_versions ) ) {
					self::$rsp->add_step(
						sprintf( 'update-disabled-%s-docker-compose', $site['site_url'] ),
						'EE\Migration\SiteContainers::update_disabled_site_compose_file',
						'EE\Migration\SiteContainers::restore_disabled_site_compose_file',
						[ $site, $docker_yml_backup ],
						[ $site ]
					);
				}
				continue;
			}

			if ( ! $updated_images || ! SiteContainers::is_site_service_image_changed( $updated_images, $site ) ) {
				continue;
			}

			$ee_site_object = SiteContainers::get_site_object( $site['site_type'] );

			// The support project's php shares the site's php alias, and nginx resolves it only at (re)load.
			$reload_nginx = in_array( $site['site_type'], [ 'wp', 'php' ], true );

			if ( $site['site_enabled'] ) {

				if ( $reload_nginx ) {
					// Undone last for this site: after the old containers are back and the support project is gone.
					self::$rsp->add_step(
						sprintf( 'reload-site-nginx-on-rollback-%s', $site['site_url'] ),
						function () {},
						'EE\Migration\SiteContainers::reload_site_nginx',
						null,
						[ $site['site_url'], $site['site_fs_path'] ]
					);
				}

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

				if ( $reload_nginx ) {
					self::$rsp->add_step(
						sprintf( 'reload-support-nginx-%s', $site['site_url'] ),
						'EE\Migration\SiteContainers::reload_support_nginx',
						null,
						[ $site['site_url'], $site['site_fs_path'] ],
						null
					);
				}
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

				if ( $reload_nginx ) {
					self::$rsp->add_step(
						sprintf( 'reload-site-nginx-%s', $site['site_url'] ),
						'EE\Migration\SiteContainers::reload_site_nginx',
						null,
						[ $site['site_url'], $site['site_fs_path'] ],
						null
					);
				}
			}
		}
	}

}
