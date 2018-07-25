<?php

namespace EE\Migration;
use EE\RevertableStepProcessor;
use function EE\Utils\default_launch;
use EE;

/**
 * Migrates existing containers to new image
 */
class Containers {

	/** @var RevertableStepProcessor */
	private static $rsp;

	public static function start_container_migration() {
		self::pull_new_images();
		self::migrate_site_containers();
		self::migrate_global_containers();
	}

	private static function migrate_global_containers() {

		// Upgrade nginx-proxy container
		$existing_nginx_proxy_image = EE::launch( 'docker inspect --format=\'{{.Config.Image}} ee-nginx-proxy', false, true );
		if ( $existing_nginx_proxy_image->return_code === 0 ) {
			self::$rsp->execute_step(
				function () {
					$EE_CONF_ROOT      = EE_CONF_ROOT;
					$nginx_proxy_image = 'easyengine/nginx-proxy:v' . EE_VERSION ;
					$ee_proxy_command = "docker run --name ee-nginx-proxy -e LOCAL_USER_ID=`id -u` -e LOCAL_GROUP_ID=`id -g` --restart=always -d -p 80:80 -p 443:443 -v $EE_CONF_ROOT/nginx/certs:/etc/nginx/certs -v $EE_CONF_ROOT/nginx/dhparam:/etc/nginx/dhparam -v $EE_CONF_ROOT/nginx/conf.d:/etc/nginx/conf.d -v $EE_CONF_ROOT/nginx/htpasswd:/etc/nginx/htpasswd -v $EE_CONF_ROOT/nginx/vhost.d:/etc/nginx/vhost.d -v /var/run/docker.sock:/tmp/docker.sock:ro -v $EE_CONF_ROOT:/app/ee4 -v /usr/share/nginx/html $nginx_proxy_image" ;

					if ( ! default_launch( $ee_proxy_command, true, true ) ) {
						throw new \Exception( ' Unable to upgrade ee-nginx-proxy container' );
					}
				},
				function () {
					$EE_CONF_ROOT      = EE_CONF_ROOT;
					$nginx_proxy_image = trim( $existing_nginx_proxy_image->stout );
					$ee_proxy_command = "docker run --name ee-nginx-proxy -e LOCAL_USER_ID=`id -u` -e LOCAL_GROUP_ID=`id -g` --restart=always -d -p 80:80 -p 443:443 -v $EE_CONF_ROOT/nginx/certs:/etc/nginx/certs -v $EE_CONF_ROOT/nginx/dhparam:/etc/nginx/dhparam -v $EE_CONF_ROOT/nginx/conf.d:/etc/nginx/conf.d -v $EE_CONF_ROOT/nginx/htpasswd:/etc/nginx/htpasswd -v $EE_CONF_ROOT/nginx/vhost.d:/etc/nginx/vhost.d -v /var/run/docker.sock:/tmp/docker.sock:ro -v $EE_CONF_ROOT:/app/ee4 -v /usr/share/nginx/html $nginx_proxy_image" ;

					if ( ! default_launch( $ee_proxy_command, true, true ) ) {
						throw new \Exception( ' Unable to restore ee-nginx-proxy container' );
					}
				}
			);
		}

		// Upgrade cron container
		$existing_cron_image = EE::launch( 'docker inspect --format=\'{{.Config.Image}} ee-cron-scheduler', false, true );
		if ( $existing_cron_image->return_code === 0 ) {
			self::$rsp->execute_step(
				function () {
					$cron_image = 'easyengine/cron:v' . EE_VERSION;
					$cron_scheduler_run_command = 'docker run --name ee-cron-scheduler --restart=always -d -v ' . EE_CONF_ROOT . '/cron:/etc/ofelia:ro -v /var/run/docker.sock:/var/run/docker.sock:ro ' . $cron_image ;

					if ( ! default_launch( $cron_scheduler_run_command, true, true ) ) {
						throw new \Exception( ' Unable to upgrade ee-cron-scheduler container' );
					}
				},
				function () {
					$cron_image = trim( $existing_cron_image->stdout );
					$cron_scheduler_run_command = 'docker run --name ee-cron-scheduler --restart=always -d -v ' . EE_CONF_ROOT . '/cron:/etc/ofelia:ro -v /var/run/docker.sock:/var/run/docker.sock:ro ' . $cron_image ;

					if ( ! default_launch( $cron_scheduler_run_command, true, true ) ) {
						throw new \Exception( ' Unable to restore ee-cron-scheduler container' );
					}
				}
			);
		}
	}

	/**
	 * Migrates all containers of existing sites
	 */
	private static function migrate_site_containers() {
		$sites       = \EE_DB::select( [ 'sitename', 'site_path', 'site_type', 'cache_type', 'is_ssl', 'db_host' ] );
		$site_docker = new \Site_Docker();

		foreach ( $sites as $site ) {

			$data[] = $site['site_type'];
			$data[] = $site['cache_type'];
			$data[] = $site['is_ssl'];
			$data[] = $site['db_host'];

			$docker_compose_contents = $site_docker->generate_docker_compose_yml( $data );
			$docker_compose_path     = $site['site_path'] . '/docker-compose.yml';
			$docker_compose_backup_path     = $site['site_path'] . '/docker-compose.yml';

			self::$rsp->execute_step(
				function () use ( $site ) {
					if ( ! default_launch( "cd ${site['site_path']} && mv docker-compose.yml docker-compose.yml._bak" ) ) {
						throw new \Exception( "Unable to find docker-compose.yml in ${site['site_path']} or couldn't create it's backup file. Ensure that EasyEngine has permission to create file there?" );
					}
				}, function () {}
			);

			self::$rsp->execute_step(
				function () use ( $site, $docker_compose_backup_path, $docker_compose_path ) {

					file_put_contents( $docker_compose_path, $docker_compose_contents );
					$container_upgraded = default_launch( "cd ${site['site_path']} && docker-compose up -d", true, true );

					if ( ! $container_upgraded ) {
						throw new \Exception( "Unable to upgrade containers of ${site['sitename']} site. Please check logs for more details." );
					}
				}, function () use ( $docker_compose_backup_path, $docker_compose_path ) {
					rename( $docker_compose_backup_path, $docker_compose_path );
					$container_downgraded = default_launch( "cd ${site['site_path']} && docker-compose up -d", true, true );

					if ( ! $container_downgraded ) {
						throw new \Exception( "Unable to downgrade containers of ${site['sitename']} site. Please check logs for more details." );
					}
				}
			);
		}
	}

	/**
	 * Pulls new images of all containers used by easyengine
	 */
	private static function pull_new_images() {
		self::pull_or_error( 'easyengine/php', 'v' . EE_VERSION );
		self::pull_or_error( 'easyengine/cron', 'v' . EE_VERSION );
		self::pull_or_error( 'easyengine/redis', 'v' . EE_VERSION );
		self::pull_or_error( 'easyengine/nginx', 'v' . EE_VERSION );
		self::pull_or_error( 'easyengine/mailhog', 'v' . EE_VERSION );
		self::pull_or_error( 'easyengine/mariadb', 'v' . EE_VERSION );
		self::pull_or_error( 'easyengine/phpmyadmin', 'v' . EE_VERSION );
		self::pull_or_error( 'easyengine/nginx-proxy', 'v' . EE_VERSION );
	}

	private static function pull_or_error( $image, $version ) {
		if ( ! default_launch( "docker pull $image:$version" , true, true ) ) {
			EE::error( "Unable to pull $image. Please check logs for more details." );
		}
	}
}
