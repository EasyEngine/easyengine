<?php

namespace EE\Migration;

use EE;
use Symfony\Component\Filesystem\Filesystem;
use Mustangostang\Spyc;

/**
 * Migrate site specific containers to new images.
 */
class SiteContainers {

	/**
	 * Get object of supported site type.
	 *
	 * @param string $site_type type of site.
	 *
	 * @return EE\Site\Type\HTML|EE\Site\Type\PHP|EE\Site\Type\WordPress
	 */
	public static function get_site_object( $site_type ) {
		$site_command = new \Site_Command();
		$site_class   = $site_command::get_site_types()[ $site_type ];

		return new $site_class();
	}

	/**
	 * Take backup of site's docker-compose.yml file
	 *
	 * @param string $source_path      path of docker-compose.yml.
	 * @param string $destination_path backup path for docker-compose.yml.
	 *
	 * @throws \Exception.
	 */
	public static function backup_site_docker_compose_file( $source_path, $destination_path ) {
		EE::debug( 'Start backing up site\'s docker-compose.yml' );
		$fs = new Filesystem();
		if ( ! $fs->exists( $source_path ) ) {
			throw new \Exception( ' site\'s docker-compose.yml does not exist' );
		}
		$fs->copy( $source_path, $destination_path, true );
		EE::debug( 'Complete backing up site\'s docker-compose.yml' );
	}

	/**
	 * Revert docker-compose.yml file from backup.
	 *
	 * @param string $source_path      path of backed up docker-compose.yml.
	 * @param string $destination_path original path of docker-compose.yml.
	 *
	 * @throws \Exception
	 */
	public static function revert_site_docker_compose_file( $source_path, $destination_path ) {
		EE::debug( 'Start restoring site\'s docker-compose.yml' );
		$fs = new Filesystem();
		if ( ! $fs->exists( $source_path ) ) {
			throw new \Exception( ' site\'s docker-compose.yml.backup does not exist' );
		}
		$fs->copy( $source_path, $destination_path, true );
		$fs->remove( $source_path );
		EE::debug( 'Complete restoring site\'s docker-compose.yml' );
	}

	/**
	 * Check if new image is available for site's services.
	 *
	 * @param array $updated_images array of updated images.
	 * @param array $site_info      array of site info.
	 *
	 * @return bool
	 */
	public static function is_site_service_image_changed( $updated_images, $site_info ) {

		chdir( $site_info['site_fs_path'] );
		$launch      = EE::launch( 'docker-compose images' );
		$lines       = explode( PHP_EOL, trim( $launch->stdout ) );
		$site_images = [];

		for ( $i = 1; $i < count( $lines ); $i ++ ) {
			$columns = preg_split( '/\s+/', $lines[ $i ] );

			if ( isset( $columns[1] ) ) {
				$site_images[] = $columns[1];
			}
		}

		$common_image = array_intersect( $updated_images, $site_images );

		if ( ! empty( $common_image ) ) {
			return true;
		}

		return false;
	}

	/** @var string[] Disabled sites whose compose file this run backed up, the only ones a rollback restores. */
	private static $backed_up = [];

	/** @var string[] Sites this run upgrades without support containers, as site URL => reason. */
	private static $without_support = [];

	/**
	 * EasyEngine images a docker-compose.yml sets, as image => tag.
	 *
	 * @param string $compose_file Path of the file.
	 *
	 * @return array
	 */
	public static function get_compose_file_images( $compose_file ) {
		try {
			// Spyc, not symfony/yaml: the phar doesn't ship symfony/yaml.
			$config = is_file( $compose_file ) ? Spyc::YAMLLoad( $compose_file ) : [];
		} catch ( \Exception $e ) {
			$config = [];
		}
		$images = [];
		if ( ! is_array( $config ) || empty( $config['services'] ) || ! is_array( $config['services'] ) ) {
			return $images;
		}
		foreach ( $config['services'] as $service ) {
			if ( is_array( $service ) && ! empty( $service['image'] ) && is_string( $service['image'] ) && 0 === strpos( $service['image'], 'easyengine/' ) ) {
				list( $image, $tag ) = explode( ':', $service['image'], 2 ) + [ 1 => '' ];
				$images[ $image ] = $tag;
			}
		}

		return $images;
	}

	/**
	 * Back up and regenerate a disabled site's docker-compose.yml with this release's images, without starting anything,
	 * so that `ee site enable` starts it on them. A failure only warns and keeps the old file: a disabled site must not
	 * block or roll back the upgrade of the others.
	 *
	 * @param array  $site_info   Site row.
	 * @param string $backup_path Where to back up its docker-compose.yml.
	 */
	public static function update_disabled_site_compose_file( $site_info, $backup_path ) {
		$compose_file = $site_info['site_fs_path'] . '/docker-compose.yml';
		$fs           = new Filesystem();
		unset( self::$backed_up[ $site_info['site_url'] ] );
		try {
			$fs->copy( $compose_file, $backup_path, true );
			self::$backed_up[ $site_info['site_url'] ] = $backup_path;
			self::generate_site_docker_compose_file( $site_info, self::get_site_object( $site_info['site_type'] ) );
			// A missing img-versions.json entry only raises a PHP warning and writes an image without a tag.
			if ( preg_match( '/^\s*image:\s*\S+:\s*$/m', (string) file_get_contents( $compose_file ) ) ) {
				throw new \Exception( 'an image has no tag' );
			}
		} catch ( \Throwable $e ) {
			try {
				if ( isset( self::$backed_up[ $site_info['site_url'] ] ) ) {
					$fs->copy( $backup_path, $compose_file, true );
				}
			} catch ( \Exception $restore_error ) {
				EE::warning( sprintf( 'Could not restore %s from %s: %s', $compose_file, $backup_path, $restore_error->getMessage() ) );
			}
			unset( self::$backed_up[ $site_info['site_url'] ] );
			$reason = '' === $e->getMessage() ? 'see the error above' : $e->getMessage();
			EE::warning( sprintf( 'Could not update docker-compose.yml of the disabled site %1$s to the new images (%2$s). Run `ee site enable %1$s --refresh` to enable it on them.', $site_info['site_url'], $reason ) );
		}
	}

	/**
	 * Rollback of update_disabled_site_compose_file(): restore the file it backed up in this run, if any.
	 *
	 * @param array $site_info Site row.
	 */
	public static function restore_disabled_site_compose_file( $site_info ) {
		if ( isset( self::$backed_up[ $site_info['site_url'] ] ) ) {
			( new Filesystem() )->copy( self::$backed_up[ $site_info['site_url'] ], $site_info['site_fs_path'] . '/docker-compose.yml', true );
			unset( self::$backed_up[ $site_info['site_url'] ] );
		}
	}

	/**
	 * Generate docker-compose.yml for specific site.
	 *
	 * @param array $site_info    array of site information.
	 * @param object $site_object Object of the particular site-type.
	 */
	public static function generate_site_docker_compose_file( $site_info, $site_object ) {
		$site_object->populate_site_info( $site_info['site_url'] );
		EE::debug( "Start generating new docker-compose.yml for ${site_info['site_url']}" );
		$site_object->dump_docker_compose_yml( [ 'nohttps' => self::is_served_without_https( $site_info ) ] );
		EE::debug( "Complete generating new docker-compose.yml for ${site_info['site_url']}" );
	}

	/**
	 * Whether the site's docker-compose.yml turns HTTPS off: without SSL, or while its Let's Encrypt certificate waits for `ee site ssl-verify`.
	 *
	 * With HTTPS on, nginx-proxy would serve a pending site with the closest matching certificate of another site.
	 *
	 * @param array $site_info array of site information.
	 *
	 * @return bool
	 */
	public static function is_served_without_https( $site_info ) {
		if ( empty( $site_info['site_ssl'] ) ) {
			return true;
		}

		// site-command versions without the pending state don't have the helper and keep HTTPS on.
		return function_exists( 'EE\Site\Utils\is_le_ssl_pending' )
			&& \EE\Site\Utils\is_le_ssl_pending( $site_info['site_url'], $site_info['site_ssl'] );
	}

	/**
	 * Enable site.
	 *
	 * @param array  $site_info   array of site information.
	 * @param object $site_object object of site-type( HTML, PHP, WordPress ).
	 *
	 * @throws \Exception
	 */
	public static function enable_default_containers( $site_info, $site_object ) {
		EE::debug( "Start enabling default containers of ${site_info['site_url']}" );

		try {
			$site_object->enable( [ $site_info['site_url'] ], [ 'force' => true ], false );
		} catch ( \Exception $e ) {
			throw new \Exception( $e->getMessage() );
		}

		EE::debug( "Complete enabling default containers of ${site_info['site_url']}" );
	}

	/**
	 * Disable site.
	 *
	 * @param array $site_info array of site information.
	 *
	 * @throws \Exception
	 */
	public static function disable_default_containers( $site_info ) {
		EE::debug( "Start disabling default containers of ${site_info['site_url']}" );

		if ( ! chdir( $site_info['site_fs_path'] ) ) {
			throw new \Exception( sprintf( '%s path does not exist', $site_info['site_fs_path'] ) );
		}

		// Same files as the site's own commands, so services added in user-docker-compose files are stopped too.
		$compose = rtrim( \EE_DOCKER::docker_compose_with_custom() );
		if ( ! EE::exec( "$compose stop && $compose rm -f" ) ) {
			throw new \Exception( sprintf( 'Something went wrong on disable site %s', $site_info['site_url'] ) );
		}

		EE::debug( "Complete disabling default containers of ${site_info['site_url']}" );
	}

	/**
	 * Function to delete given volume.
	 *
	 * @param string $volume_name  Name of the volume to be deleted.
	 * @param string $symlink_path Corresponding symlink to be removed.
	 */
	public static function delete_volume( $volume_name, $symlink_path ) {
		$fs = new Filesystem();
		\EE::exec( 'docker volume rm ' . $volume_name );
		$fs->remove( $symlink_path );
	}

	/**
	 * Function to create given volume.
	 *
	 * @param string|array $site   Name of the site or array of site having site_url.
	 * @param string $volume_name  Name of the volume to be created.
	 * @param string $symlink_path Corresponding symlink to be created.
	 */
	public static function create_volume( $site, $volume_name, $symlink_path ) {
		$site_url = is_array( $site ) ? $site['site_url'] : $site;
		$volumes  = [
			[
				'name'            => $volume_name,
				'path_to_symlink' => $symlink_path,
			],
		];
		\EE_DOCKER::create_volumes( $site_url, $volumes );
	}

	/**
	 * Function to backup and restore file/directory.
	 *
	 * @param string $destination    Destination path.
	 * @param string $source         Source path.
	 * @param bool $delete_different Delete files in $destination that are not there in source.
	 */
	public static function backup_restore( $source, $destination = '', $delete_different = true ) {
		$fs          = new Filesystem();
		$destination = empty( $destination ) ? EE_BACKUP_DIR . '/' . basename( $source ) : $destination;
		EE::debug( "Copying files from: $source to $destination" );
		if ( is_file( $source ) ) {
			$fs->copy( $source, $destination, true );
		} else {
			$copy_options = [
				'override' => true,
				'delete'   => $delete_different,
			];
			$fs->mirror( $source, $destination, null, $copy_options );
		}
	}

	/**
	 * Function to delete file/directory.
	 *
	 * @param string|array $path_to_delete File(s)/Director(y/ies) to be deleted.
	 */
	public static function delete( $path_to_delete ) {
		$fs = new Filesystem();
		$fs->remove( $path_to_delete );
	}

	/**
	 * Function to reload site's nginx.
	 *
	 * @param string $site_fs_path Directory containing site's docker-compose.yml.
	 */
	public static function reload_nginx( $site_fs_path ) {

		chdir( $site_fs_path );
		$success = EE::exec( "docker-compose exec nginx sh -c 'nginx -t && nginx -s reload'" );
		if ( ! $success ) {
			throw new \Exception( 'Could not reload nginx. Check logs.' );
		}
	}

	/**
	 * Reload site's nginx after its support containers are removed, so it drops the support php's IP.
	 * A failure only warns: the site works, nginx just keeps retrying the dead peer.
	 *
	 * @param string $site_url     Site URL.
	 * @param string $site_fs_path Directory containing site's docker-compose.yml.
	 */
	public static function reload_site_nginx( $site_url, $site_fs_path ) {
		EE::debug( sprintf( 'Start reloading nginx of %s', $site_url ) );

		try {
			self::reload_nginx( $site_fs_path );
		} catch ( \Exception $e ) {
			EE::warning( sprintf( 'Could not reload nginx of %1$s after recreating its containers. Some requests may take ~3 s until you run `ee site reload %1$s --nginx`.', $site_url ) );

			return;
		}

		EE::debug( sprintf( 'Complete reloading nginx of %s', $site_url ) );
	}

	/**
	 * Reload the support nginx after the site's containers are removed, so it drops the old php's IP.
	 * Best effort: the support nginx only serves until the site's containers are back.
	 *
	 * @param string $site_url     Site URL.
	 * @param string $site_fs_path Directory containing site's docker-compose.yml.
	 */
	public static function reload_support_nginx( $site_url, $site_fs_path ) {
		if ( isset( self::$without_support[ $site_url ] ) ) {
			return;
		}
		if ( ! chdir( $site_fs_path ) || ! EE::exec( self::get_support_compose( $site_url ) . " exec nginx sh -c 'nginx -t && nginx -s reload'" ) ) {
			EE::debug( sprintf( 'Could not reload support nginx of %s', $site_url ) );
		}
	}

	/**
	 * Function to reload site's php.
	 *
	 * @param string $site_fs_path Directory containing site's docker-compose.yml.
	 */
	public static function reload_php( $site_fs_path ) {

		chdir( $site_fs_path );
		$success = EE::exec( "docker-compose exec php bash -c 'kill -USR2 1'" );
		if ( ! $success ) {
			throw new \Exception( 'Could not reload php. Check logs.' );
		}
	}

	/**
	 * Function to pull site docker-compose images.
	 *
	 * @param string $site_fs_path   Directory containing site's docker-compose.yml.
	 */
	public static function docker_compose_pull( $site_fs_path ) {

		chdir( $site_fs_path );
		$success = EE::exec( "docker-compose pull" );
		if ( ! $success ) {
			throw new \Exception( 'Could pull given images.' );
		}
	}

	/**
	 * Enable support containers for sites.
	 *
	 * @param $site_url string Site URL.
	 * @param $site_fs_path string File system path of site.
	 *
	 * @throws \Exception
	 */
	public static function enable_support_containers( $site_url, $site_fs_path ) {
		EE::debug( sprintf( 'Start enabling containers for %s', $site_url ) );

		// Also when called as a rollback: a site upgraded without support containers never gets them in this run.
		if ( isset( self::$without_support[ $site_url ] ) ) {
			return;
		}

		if ( ! chdir( $site_fs_path ) ) {
			throw new \Exception( sprintf( '%s does not exist.', $site_fs_path ) );
		}

		$compose  = self::get_support_compose( $site_url );
		$config   = self::get_compose_config( $compose );
		$services = self::get_support_services( $config );
		if ( empty( $services ) ) {
			EE::warning( sprintf( 'Could not read the docker-compose config of %s, starting its support nginx with all its dependencies.', $site_url ) );
			$command = $compose . ' up -d nginx';
		} else {
			$clashes = self::get_support_clashes( $config, $services );
			if ( $clashes ) {
				self::upgrade_without_support( $site_url, implode( ', ', $clashes ) . ', which would clash with its running containers' );

				return;
			}
			$command = $compose . ' up -d --no-deps ' . implode( ' ', array_map( 'escapeshellarg', $services ) );
		}
		if ( ! EE::exec( $command ) ) {
			// Remove what did start, or keep failing the step if that leaves support containers behind.
			if ( ! EE::exec( "$compose stop && $compose rm -f" ) ) {
				throw new \Exception( sprintf( 'Unable to create support container for %s', $site_url ) );
			}
			self::upgrade_without_support( $site_url, 'its support containers failed to start' );

			return;
		}

		EE::debug( sprintf( 'Complete enabling support containers for %s', $site_url ) );
	}

	/**
	 * Disable support containers for sites.
	 *
	 * @param $site_url string Site URL.
	 * @param $site_fs_path string File system path of site.
	 *
	 * @throws \Exception
	 */
	public static function disable_support_containers( $site_url, $site_fs_path ) {
		EE::debug( sprintf( 'Start disabling support containers for %s', $site_url ) );

		if ( isset( self::$without_support[ $site_url ] ) ) {
			return;
		}

		if ( ! chdir( $site_fs_path ) ) {
			throw new \Exception( sprintf( '%s does not exist.', $site_fs_path ) );
		}

		$compose = self::get_support_compose( $site_url );
		if ( ! EE::exec( "$compose stop && $compose rm -f" ) ) {
			throw new \Exception( sprintf( 'Unable to remove support container for %s', $site_url ) );
		}

		EE::debug( sprintf( 'Complete disabling support containers for %s', $site_url ) );
	}

	/**
	 * docker-compose command of a site's support project, with the same compose files as the site's own commands.
	 * Run it in the site directory: the user-docker-compose paths are relative.
	 *
	 * @param string $site_url Site URL.
	 *
	 * @return string
	 */
	private static function get_support_compose( $site_url ) {
		return rtrim( \EE_DOCKER::docker_compose_with_custom() ) . ' --project-name=' . self::get_support_project_name( $site_url );
	}

	/**
	 * Merged config of a compose command's files: depends_on from every file, always in map form.
	 *
	 * @param string $compose docker-compose command.
	 *
	 * @return array|null Null when compose can't render it.
	 */
	private static function get_compose_config( $compose ) {
		$result = EE::launch( $compose . ' config --format json' );
		$config = 0 === $result->return_code ? json_decode( $result->stdout, true ) : null;

		return is_array( $config ) ? $config : null;
	}

	/**
	 * Services of a site's support project: nginx and what it depends on, except the local db. A support db would be a
	 * second MariaDB on the site's db_data volume, answering to the same `db` alias as the site's own.
	 *
	 * @param array|null $config Merged compose config of the site (see get_compose_config()).
	 *
	 * @return string[] Empty when there is no config or it has no nginx service.
	 */
	private static function get_support_services( $config ) {
		if ( ! is_array( $config ) || empty( $config['services'] ) || ! is_array( $config['services'] ) || ! isset( $config['services']['nginx'] ) ) {
			return [];
		}

		$services = [];
		$pending  = [ 'nginx' ];
		while ( $pending ) {
			$service = array_shift( $pending );
			if ( ! is_string( $service ) || 'db' === $service || in_array( $service, $services, true ) || ! isset( $config['services'][ $service ] ) ) {
				continue;
			}
			$services[] = $service;
			if ( isset( $config['services'][ $service ]['depends_on'] ) && is_array( $config['services'][ $service ]['depends_on'] ) ) {
				$pending = array_merge( $pending, array_keys( $config['services'][ $service ]['depends_on'] ) );
			}
		}

		return $services;
	}

	/**
	 * What in the support services can't run next to the site's own containers: published host ports and fixed
	 * container names, which only a site's custom compose files set.
	 *
	 * @param array    $config   Merged compose config of the site.
	 * @param string[] $services Support services.
	 *
	 * @return string[] One description per clash, empty when there is none.
	 */
	private static function get_support_clashes( $config, $services ) {
		$clashes = [];
		foreach ( $services as $service ) {
			$definition = $config['services'][ $service ];
			foreach ( isset( $definition['ports'] ) && is_array( $definition['ports'] ) ? $definition['ports'] : [] as $port ) {
				// A port without a published one gets a free host port.
				if ( is_array( $port ) && isset( $port['published'] ) && '' !== (string) $port['published'] ) {
					$clashes[] = sprintf( '%s publishes host port %s', $service, $port['published'] );
				}
			}
			if ( ! empty( $definition['container_name'] ) && is_string( $definition['container_name'] ) ) {
				$clashes[] = sprintf( '%s has the fixed container name %s', $service, $definition['container_name'] );
			}
		}

		return $clashes;
	}

	/**
	 * Upgrade a site without support containers: the rest of its steps then stop and recreate its own containers.
	 *
	 * @param string $site_url Site URL.
	 * @param string $reason   Why it has none.
	 */
	private static function upgrade_without_support( $site_url, $reason ) {
		self::$without_support[ $site_url ] = $reason;
		EE::warning( sprintf( 'Upgrading %s without support containers because %s. The site is down for a few seconds while its containers are recreated.', $site_url, $reason ) );
	}

	/**
	 * Compose project name of a site's support containers.
	 *
	 * @param string $site_url Site URL.
	 *
	 * @return string
	 */
	private static function get_support_project_name( $site_url ) {
		return sprintf( 'update-ee-%s', str_replace( '.', '', $site_url ) );
	}
}
