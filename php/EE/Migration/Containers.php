<?php

namespace EE\Migration;

use EE\RevertableStepProcessor;
use function EE\Utils\default_launch;
use EE;

/**
 * Migrates existing containers to new image
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
		self::pull_new_images();
		self::migrate_site_containers();
		self::migrate_global_containers();
		if ( ! self::$rsp->execute() ) {
			throw new \Exception( 'Unable to migrate sites to newer version' );
		}

		EE\Utils\delem_log( 'Container migration completed' );
	}

	/**
	 * Pulls new images of all containers used by easyengine
	 */
	private static function pull_new_images() {

		$img_versions = EE\Utils\get_image_versions();		
		self::pull_or_error( 'easyengine/php', $img_versions['easyengine/php'] );
		self::pull_or_error( 'easyengine/cron', $img_versions['easyengine/cron'] );
		self::pull_or_error( 'easyengine/redis', $img_versions['easyengine/redis'] );
		self::pull_or_error( 'easyengine/nginx', $img_versions['easyengine/nginx'] );
		self::pull_or_error( 'easyengine/postfix', $img_versions['easyengine/postfix'] );
		self::pull_or_error( 'easyengine/mailhog', $img_versions['easyengine/mailhog'] );
		self::pull_or_error( 'easyengine/mariadb', $img_versions['easyengine/mariadb'] );
		self::pull_or_error( 'easyengine/phpmyadmin', $img_versions['easyengine/phpmyadmin'] );
		self::pull_or_error( 'easyengine/nginx-proxy', $img_versions['easyengine/nginx-proxy'] );
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
		if ( ! default_launch( "docker pull $image:$version" ) ) {
			throw new \Exception( "Unable to pull $image. Please check logs for more details." );
		}
	}

	/**
	 * Migrates all containers of existing sites
	 */
	private static function migrate_site_containers() {
		$sites       = \EE_DB::select( [ 'site_url', 'site_fs_path', 'site_type', 'cache_nginx_browser', 'site_ssl', 'db_host' ] );
		$site_docker = new \Site_Docker();

		foreach ( $sites as $site ) {

			$data   = [];
			$data[] = $site['site_type'];
			$data[] = $site['cache_nginx_browser'];
			$data[] = $site['site_ssl'];
			$data[] = $site['db_host'];

			$docker_compose_contents    = $site_docker->generate_docker_compose_yml( $data );
			$docker_compose_path        = $site['site_fs_path'] . '/docker-compose.yml';
			$docker_compose_backup_path = $site['site_fs_path'] . '/docker-compose.yml.bak';

			self::$rsp->add_step(
				"upgrade-${site['site_url']}-copy-compose-file",
				'EE\Migration\Containers::site_copy_compose_file_up',
				null,
				[ $site, $docker_compose_path, $docker_compose_backup_path ]
			);

			self::$rsp->add_step(
				"upgrade-${site['site_url']}-containers",
				'EE\Migration\Containers::site_containers_up',
				'EE\Migration\Containers::site_containers_down',
				[ $site, $docker_compose_backup_path, $docker_compose_path, $docker_compose_contents ],
				[ $site, $docker_compose_backup_path, $docker_compose_path ]
			);
		}
	}

	/**
	 * Migrates global containers. These are container which are not created per site (i.e. ee-cron-scheduler)
	 */
	private static function migrate_global_containers() {

		// Upgrade nginx-proxy container
		$existing_nginx_proxy_image = EE::launch( sprintf( 'docker inspect --format=\'{{.Config.Image}}\' %1$s', EE_PROXY_TYPE ), false, true );
		if ( 0 === $existing_nginx_proxy_image->return_code ) {
			self::$rsp->add_step(
				'upgrade-nginxproxy-container',
				'EE\Migration\Containers::nginxproxy_container_up',
				'EE\Migration\Containers::nginxproxy_container_down',
				null,
				[ $existing_nginx_proxy_image ]
			);
		}

		// Upgrade cron container
		$existing_cron_image = EE::launch( 'docker inspect --format=\'{{.Config.Image}}\' ee-cron-scheduler', false, true );
		if ( 0 === $existing_cron_image->return_code ) {
			self::$rsp->add_step(
				'upgrade-cron-container',
				'EE\Migration\Containers::cron_container_up',
				'EE\Migration\Containers::cron_container_down',
				null,
				[ $existing_cron_image ]
			);
		}
	}

	/**
	 * Upgrades nginx-proxy container
	 *
	 * @throws \Exception
	 */
	public static function nginxproxy_container_up() {
		$EE_ROOT_DIR       = EE_ROOT_DIR;
		$nginx_proxy_image = 'easyengine/nginx-proxy:v' . EE_VERSION;
		$ee_proxy_command  = sprintf( 'docker run --name %1$s -e LOCAL_USER_ID=`id -u` -e LOCAL_GROUP_ID=`id -g` --restart=always -d -p 80:80 -p 443:443 -v %2$s/nginx/certs:/etc/nginx/certs -v %2$s/nginx/dhparam:/etc/nginx/dhparam -v %2$s/nginx/conf.d:/etc/nginx/conf.d -v %2$s/nginx/htpasswd:/etc/nginx/htpasswd -v %2$s/nginx/vhost.d:/etc/nginx/vhost.d -v /var/run/docker.sock:/tmp/docker.sock:ro -v %2$s:/app/ee4 -v /usr/share/nginx/html %3$s', EE_PROXY_TYPE, $EE_ROOT_DIR, $nginx_proxy_image );

		default_launch( sprintf( 'docker rm -f %1$s', EE_PROXY_TYPE ), false, true );

		if ( ! default_launch( $ee_proxy_command, false, true ) ) {
			throw new \Exception( sprintf( 'Unable to upgrade %1$s container', EE_PROXY_TYPE ) );
		}

	}

	/**
	 * Downgrades nginx-proxy container
	 *
	 * @param $existing_nginx_proxy_image Old nginx-proxy image name
	 *
	 * @throws \Exception
	 */
	public static function nginxproxy_container_down( $existing_nginx_proxy_image ) {
		$EE_ROOT_DIR       = EE_ROOT_DIR;
		$nginx_proxy_image = trim( $existing_nginx_proxy_image->stout );
		$ee_proxy_command  = sprintf( 'docker run --name %1$s -e LOCAL_USER_ID=`id -u` -e LOCAL_GROUP_ID=`id -g` --restart=always -d -p 80:80 -p 443:443 -v %2$s/nginx/certs:/etc/nginx/certs -v %2$s/nginx/dhparam:/etc/nginx/dhparam -v %2$s/nginx/conf.d:/etc/nginx/conf.d -v %2$s/nginx/htpasswd:/etc/nginx/htpasswd -v %2$s/nginx/vhost.d:/etc/nginx/vhost.d -v /var/run/docker.sock:/tmp/docker.sock:ro -v %2$s:/app/ee4 -v /usr/share/nginx/html %3$s', EE_PROXY_TYPE, $EE_ROOT_DIR, $nginx_proxy_image );

		default_launch( sprintf( 'docker rm -f %1$s', EE_PROXY_TYPE ), false, true );

		if ( ! default_launch( $ee_proxy_command, false, true ) ) {
			throw new \Exception( sprintf( 'Unable to restore %1$s container', EE_PROXY_TYPE ) );
		}
	}

	/**
	 * Upgrades ee-cron-scheduler container
	 *
	 * @throws \Exception
	 */
	public static function cron_container_up() {
		$cron_image                 = 'easyengine/cron:v' . EE_VERSION;
		$cron_scheduler_run_command = 'docker run --name ee-cron-scheduler --restart=always -d -v ' . EE_ROOT_DIR . '/cron:/etc/ofelia:ro -v /var/run/docker.sock:/var/run/docker.sock:ro ' . $cron_image;

		default_launch( 'docker rm -f ee-cron-scheduler', false, true );

		if ( ! default_launch( $cron_scheduler_run_command, false, true ) ) {
			throw new \Exception( ' Unable to upgrade ee-cron-scheduler container' );
		}
	}

	/**
	 * Downgrades ee-cron-scheduler container
	 *
	 * @param $existing_cron_image Old nginx-proxy image name
	 *
	 * @throws \Exception
	 */
	public static function cron_container_down( $existing_cron_image ) {
		$cron_image                 = trim( $existing_cron_image->stdout );
		$cron_scheduler_run_command = 'docker run --name ee-cron-scheduler --restart=always -d -v ' . EE_ROOT_DIR . '/cron:/etc/ofelia:ro -v /var/run/docker.sock:/var/run/docker.sock:ro ' . $cron_image;

		default_launch( 'docker rm -f ee-cron-scheduler', false, true );

		if ( ! default_launch( $cron_scheduler_run_command, false, true ) ) {
			throw new \Exception( ' Unable to restore ee-cron-scheduler container' );
		}
	}

	/**
	 * Copies updated docker-compose file site root
	 *
	 * @param $site Name of site
	 * @param $docker_compose_path Path where docker-compose.yml is to be copied
	 * @param $docker_compose_backup_path Path old docker-compose.yml is to be copied for backup
	 *
	 * @throws \Exception
	 */
	public static function site_copy_compose_file_up( $site, $docker_compose_path, $docker_compose_backup_path ) {
		if ( ! default_launch( "cp $docker_compose_path $docker_compose_backup_path" ) ) {
			throw new \Exception( "Unable to find docker-compose.yml in ${site['site_fs_path']} or couldn't create it's backup file. Ensure that EasyEngine has permission to create file there" );
		}
	}

	/**
	 * Upgrades site container of a site
	 *
	 * @param $site Name of site
	 * @param $docker_compose_backup_path Path of old docker-compose.yml file
	 * @param $docker_compose_path Path of updated docker-compose.yml file
	 * @param $docker_compose_contents Contents of updated docker-compose.yml file
	 *
	 * @throws \Exception
	 */
	public static function site_containers_up( $site, $docker_compose_backup_path, $docker_compose_path, $docker_compose_contents ) {
		file_put_contents( $docker_compose_path, $docker_compose_contents );
		$container_upgraded = default_launch( "cd ${site['site_fs_path']} && docker-compose up -d" );

		if ( ! $container_upgraded ) {
			throw new \Exception( "Unable to upgrade containers of site: ${site['site_url']}. Please check logs for more details." );
		}
	}

	/**
	 * Downgrades container of a site.
	 *
	 * @param $site Name of site
	 * @param $docker_compose_backup_path Path of old docker-compose.yml file
	 * @param $docker_compose_path Path of updated docker-compose.yml file
	 *
	 * @throws \Exception
	 */
	public static function site_containers_down( $site, $docker_compose_backup_path, $docker_compose_path ) {
		rename( $docker_compose_backup_path, $docker_compose_path );
		$container_downgraded = default_launch( "cd ${site['site_fs_path']} && docker-compose up -d" );

		if ( ! $container_downgraded ) {
			throw new \Exception( "Unable to downgrade containers of ${site['site_url']} site. Please check logs for more details." );
		}
	}
}
