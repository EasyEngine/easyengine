<?php

namespace EE\Migration;

use EE;
use Symfony\Component\Filesystem\Filesystem;

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
		$launch   = EE::launch( 'docker-compose config --services' );
		$services = explode( PHP_EOL, trim( $launch->stdout ) );

		$site_images = array_map( function ( $service ) {
			return 'easyengine/' . $service;
		}, $services );

		$common_image = array_intersect( $updated_images, $site_images );

		if ( ! empty( $common_image ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Generate docker-compose.yml for specific site.
	 *
	 * @param array $site_info    array of site information.
	 * @param object $site_object Object of the particular site-type.
	 */
	public static function generate_site_docker_compose_file( $site_info, $site_object ) {
		$site_object->populate_site_info( $site_info['site_url'] );
		EE::debug( "Start generating news docker-compose.yml for ${site_info['site_url']}" );
		$site_object->dump_docker_compose_yml( [ 'nohttps' => ! $site_info['site_ssl'] ] );
		EE::debug( "Complete generating news docker-compose.yml for ${site_info['site_url']}" );
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

		if ( ! EE::exec( 'docker-compose stop && docker-compose rm -f' ) ) {
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
	 * @param string $site_fs_path   Directory containing site's docker-compose.yml.
	 */
	public static function reload_nginx( $site_fs_path ) {

		chdir( $site_fs_path );
		$success = EE::exec( "docker-compose exec nginx sh -c 'nginx -t && service openresty reload'" );
		if ( ! $success ) {
			throw new \Exception( 'Could not reload nginx. Check logs.' );
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

		$site_name    = str_replace( '.', '', $site_url );
		$project_name = sprintf( 'update-ee-%s', $site_name );

		if ( ! chdir( $site_fs_path ) ) {
			throw new \Exception( sprintf( '%s does not exist.', $site_fs_path ) );
		}

		$command = sprintf( 'docker-compose --project-name=%s up -d nginx', $project_name );
		if ( ! EE::exec( $command ) ) {
			throw new \Exception( sprintf( 'Unable to create support container for %s', $site_url ) );
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

		$site_name    = str_replace( '.', '', $site_url );
		$project_name = sprintf( 'update-ee-%s', $site_name );

		if ( ! chdir( $site_fs_path ) ) {
			throw new \Exception( sprintf( '%s does not exist.', $site_fs_path ) );
		}

		$command = sprintf( 'docker-compose --project-name=%1$s stop && docker-compose --project-name=%1$s rm -f', $project_name );
		if ( ! EE::exec( $command ) ) {
			throw new \Exception( sprintf( 'Unable to remove support container for %s', $site_url ) );
		}

		EE::debug( sprintf( 'Complete disabling support containers for %s', $site_url ) );
	}
}
