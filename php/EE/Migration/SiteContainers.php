<?php

namespace EE\Migration;

use EE;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Migrate site specific containers to new images.
 */
class SiteContainers {

	/**
	 * Take backup of site's docker-compose.yml file
	 *
	 * @param $source_path string path of docker-compose.yml
	 * @param $dest_path   string backup path for docker-compose.yml
	 *
	 * @throws \Exception
	 */
	public static function backup_site_docker_compose_file( $source_path, $dest_path ) {
		EE::debug( 'Start backing up site\'s docker-compose.yml' );
		$fs = new Filesystem();
		if ( ! $fs->exists( $source_path ) ) {
			throw new \Exception( ' site\'s docker-compose.yml does not exist' );
		}
		$fs->copy( $source_path, $dest_path, true );
		EE::debug( 'Complete backing up site\'s docker-compose.yml' );
	}

	/**
	 * Revert docker-compose.yml file from backup.
	 *
	 * @param $source_path string path of backed up docker-compose.yml
	 * @param $dest_path   string original path of docker-compose.yml
	 *
	 * @throws \Exception
	 */
	public static function revert_site_docker_compose_file( $source_path, $dest_path ) {
		EE::debug( 'Start restoring site\'s docker-compose.yml' );
		$fs = new Filesystem();
		if ( ! $fs->exists( $source_path ) ) {
			throw new \Exception( ' site\'s docker-compose.yml.backup does not exist' );
		}
		$fs->copy( $source_path, $dest_path, true );
		$fs->remove( $source_path );
		EE::debug( 'Complete restoring site\'s docker-compose.yml' );
	}

	/**
	 * Check if new image is available for site's services.
	 *
	 * @param $updated_images array of updated images.
	 * @param $site_info      array of site info
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
	 * @param $site_info array of site information.
	 */
	public static function generate_site_docker_compose_file( $site_info ) {
		EE::debug( "Start generating news docker-compose.yml for ${site_info['site_url']}" );
		$site_docker        = self::get_site_docker_object( $site_info['site_type'] );
		$filters            = self::get_site_filters( $site_info );
		$docker_yml_content = $site_docker->generate_docker_compose_yml( $filters );

		$fs = new Filesystem();
		$fs->dumpFile( $site_info['site_fs_path'] . '/docker-compose.yml', $docker_yml_content );
		EE::debug( "Complete generating news docker-compose.yml for ${site_info['site_url']}" );
	}

	/**
	 * Enable site.
	 *
	 * @param $site_info   array of site information.
	 * @param $site_object object of site-type( HTML, PHP, Wordpress ).
	 */
	public static function enable_site( $site_info, $site_object ) {
		EE::debug( "Start enabling ${site_info['site_url']}" );
		$site_object->enable( [ $site_info['site_url'] ], [] );
		EE::debug( "Complete enabling ${site_info['site_url']}" );
	}

	/**
	 * Disable site.
	 *
	 * @param $site_info   array of site information.
	 * @param $site_object object of site-type( HTML, PHP, Wordpress ).
	 */
	public static function disable_site( $site_info, $site_object ) {
		EE::debug( "Start disabling ${site_info['site_url']}" );
		$site_object->disable( [ $site_info['site_url'] ], [] );
		EE::debug( "Complete disabling ${site_info['site_url']}" );
	}

	/**
	 * Get object of supported site type.
	 *
	 * @param $site_type string type of site.
	 *
	 * @return EE\Site\Type\HTML|EE\Site\Type\PHP|EE\Site\Type\WordPress
	 */
	public static function get_site_object( $site_type ) {
		switch ( $site_type ) {
			case 'html':
				return new EE\Site\Type\HTML();

			case 'php':
				return new EE\Site\Type\PHP();

			case 'wp':
				return new EE\Site\Type\WordPress();
		}
	}

	/**
	 * Get site type specific filters.
	 *
	 * @param $site array of site information.
	 *
	 * @return array
	 */
	public static function get_site_filters( $site ) {
		$filters = [];

		switch ( $site['site_type'] ) {
			case 'html':
				$filters[] = $site['site_type'];

				break;

			case 'php':
				$filters[] = $site['cache_host'];
				if ( 'mysql' === $site['app_sub_type'] ) {
					$filters[] = $site['db_host'];
				}

				break;

			case 'wp':
				$filters[] = $site['app_sub_type'];
				$filters[] = $site['cache_host'];
				$filters[] = $site['db_host'];

				break;
		}

		$filters['nohttps'] = $site['site_ssl'] ? false : true;

		return $filters;
	}

	/**
	 * get site docker object of specific site type.
	 *
	 * @param $site_type string type of site.
	 *
	 * @return EE\Site\Type\Site_HTML_Docker|EE\Site\Type\Site_PHP_Docker|EE\Site\Type\Site_WP_Docker
	 */
	public static function get_site_docker_object( $site_type ) {

		switch ( $site_type ) {
			case 'html':
				return new EE\Site\Type\Site_HTML_Docker();

			case 'php':
				return new EE\Site\Type\Site_PHP_Docker();

			case 'wp':
				return new EE\Site\Type\Site_WP_Docker();
		}
	}
}
