<?php

namespace EE\Migration;
use function EE\Utils\default_launch;

/**
 * Migrates existing containers to new image
 */
class Containers {

	public static function start_container_migration() {
		self::pull_new_images();

		$sites       = \EE_DB::select( [ 'sitename', 'site_path', 'site_type', 'cache_type', 'is_ssl', 'db_host' ] );
		$site_docker = new \Site_Docker();

		foreach ( $sites as $site ) {

			$data[] = $site['site_type'];
			$data[] = $site['cache_type'];
			$data[] = $site['is_ssl'];
			$data[] = $site['db_host'];

			$docker_compose_contents = $site_docker->generate_docker_compose_yml( $data );
			$docker_compose_path     = $site['site_path'] . '/docker-compose.yml';

			if ( ! default_launch( "cd ${site['site_path']} && mv docker-compose.yml docker-compose.yml._bak" ) ) {
				EE::error( "Unable to find docker-compose.yml in ${site['site_path']} or couldn't create it's backup file. Ensure that EasyEngine has permission to create file there?" );
			}

			file_put_contents( $docker_compose_path, $docker_compose_contents );

			$container_upgraded = default_launch( "cd ${site['site_path']} && docker-compose up -d", true, true );

			if ( ! $container_upgraded ) {
				EE::error( "Unable to upgrade containers of ${site['sitename']} site. Please check logs for more details." );
			}
		}
	}

	private static function pull_new_images() {
		self::pull_or_error( 'easyengine/php', 'v' . EE_VERSION );
		self::pull_or_error( 'easyengine/cron', 'v' . EE_VERSION );
		self::pull_or_error( 'easyengine/redis', 'v' . EE_VERSION );
		self::pull_or_error( 'easyengine/nginx', 'v' . EE_VERSION );
		self::pull_or_error( 'easyengine/mailhog', 'v' . EE_VERSION );
		self::pull_or_error( 'easyengine/mariadb', 'v' . EE_VERSION );
		self::pull_or_error( 'easyengine/phpmyadmin', 'v' . EE_VERSION );
	}

	private static function pull_or_error( $image, $version ) {
		if ( ! default_launch( "docker pull $image:$version" , true, true ) ) {
			EE::error( "Unable to pull $image. Please check logs for more details." );
		}
	}
}
