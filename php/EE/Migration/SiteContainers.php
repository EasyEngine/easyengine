<?php

namespace EE\Migration;

use EE;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Migrates existing containers to new image
 */
class SiteContainers {

	public static function backup_site_docker_compose_file( $source_path, $dest_path ) {
		if ( ! EE::exec( "cp $source_path $dest_path" ) ) {
			throw new \Exception( "Unable to find site's docker-compose.yml or couldn't create it's backup file. Ensure that EasyEngine has permission to create file there" );
		}
	}

	public static function revert_site_docker_compose_file($source_path, $dest_path ) {
		rename( $source_path, $dest_path );

	}

	public static function is_site_service_image_changed( $changed_images, $site_info ) {
		chdir( $site_info['site_fs_path'] );
		$launch   = EE::launch( 'docker-compose config --services' );
		$services = explode( PHP_EOL, trim( $launch->stdout ) );

		$site_images = array_map( function ( $service ) {
			return 'easyengine/' . $service;
		}, $services );

		$common_image = array_intersect( $changed_images, $site_images );

		if ( ! empty( $common_image ) ) {
			return true;
		}
		return false;
	}

	public static function generate_site_docker_compose_file( $site_info ) {

		$filters = self::get_site_filters( $site_info );

		$site_docker = self::get_site_docker_object( $site_info['site_type'] );

		$docker_yml_content = $site_docker->generate_docker_compose_yml($filters);

		$fs = new Filesystem();
		$fs->dumpFile( $site_info['site_fs_path'] . '/docker-compose.yml', $docker_yml_content );

	}

	public static function enable_site( $site_info, $site_object ) {
		$site_object->enable( [ $site_info['site_url'] ], [] );

	}

	public static function disable_site( $site_info, $site_object ) {
		$site_object->disable( [ $site_info['site_url'] ], [] );
	}

	public static function get_site_object($site_type) {
		switch($site_type) {
			case 'html':
				return new EE\Site\Type\HTML();

			case 'php':
				return new EE\Site\Type\PHP();

			case 'wp':
				return new EE\Site\Type\WordPress();
		}
	}

	public static function get_site_filters($site) {
		$filters = [];

		switch( $site['site_type']) {
			case 'html':
				$filters[] = $site['site_type'];
				break;

			case 'php':
				$filters[] = $site['cache_host'];
				if( 'mysql' === $site['app_sub_type']) {
					$filters[] = $site['db_host'];
				}

				break;

			case 'wordpress':
				$filters[] = $site['app_sub_type'];
				$filters[] = $site['cache_host'];
				$filters[] = $site['db_host'];

				break;
		}

		$filters['nohttps'] = false;

		if( 1 === $site['site_ssl']) {
			$filters['nohttps'] = true;
		}

		return $filters;
	}

	public static function get_site_docker_object( $site_type ){

		switch ($site_type) {
			case 'html':
				return new EE\Site\Type\Site_HTML_Docker();

			case 'php':
				return new EE\Site\Type\Site_PHP_Docker();

			case 'wp':
				return new EE\Site\Type\Site_WP_Docker();
		}
	}
}
