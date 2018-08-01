<?php

namespace EE\Migration;
use EE\RevertableStepProcessor;
use function EE\Utils\default_launch;
use EE;

/**
 * Migrates existing containers to new image
 */
class Containers {

	/** @var RevertableStepProcessor $rsp Keeps track of  */
	private static $rsp;

	public static function start_container_migration() {
		EE\Utils\delem_log( 'Starting container migration' );

		self::$rsp = new RevertableStepProcessor();
		self::pull_new_images();
		self::migrate_site_containers();
		self::migrate_global_containers();
		if( ! self::$rsp->execute() ) {
			throw new \Exception( "Unable to migrate sites to newer version" );
		}

		EE\Utils\delem_log( 'Container migration completed' );
	}

	private static function migrate_global_containers() {

		// Upgrade nginx-proxy container
		$existing_nginx_proxy_image = EE::launch( 'docker inspect --format=\'{{.Config.Image}}\' ee-nginx-proxy', false, true );
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

	public static function nginxproxy_container_up() {
		$EE_CONF_ROOT      = EE_CONF_ROOT;
		$nginx_proxy_image = 'easyengine/nginx-proxy:v' . EE_VERSION ;
		$ee_proxy_command = "docker run --name ee-nginx-proxy -e LOCAL_USER_ID=`id -u` -e LOCAL_GROUP_ID=`id -g` --restart=always -d -p 80:80 -p 443:443 -v $EE_CONF_ROOT/nginx/certs:/etc/nginx/certs -v $EE_CONF_ROOT/nginx/dhparam:/etc/nginx/dhparam -v $EE_CONF_ROOT/nginx/conf.d:/etc/nginx/conf.d -v $EE_CONF_ROOT/nginx/htpasswd:/etc/nginx/htpasswd -v $EE_CONF_ROOT/nginx/vhost.d:/etc/nginx/vhost.d -v /var/run/docker.sock:/tmp/docker.sock:ro -v $EE_CONF_ROOT:/app/ee4 -v /usr/share/nginx/html $nginx_proxy_image" ;

		default_launch( 'docker rm -f ee-nginx-proxy', false, true );

		if ( ! default_launch( $ee_proxy_command, false, true ) ) {
			throw new \Exception( ' Unable to upgrade ee-nginx-proxy container' );
		}

	}

	public static function nginxproxy_container_down( $existing_nginx_proxy_image ) {
		$EE_CONF_ROOT      = EE_CONF_ROOT;
		$nginx_proxy_image = trim( $existing_nginx_proxy_image->stout );
		$ee_proxy_command = "docker run --name ee-nginx-proxy -e LOCAL_USER_ID=`id -u` -e LOCAL_GROUP_ID=`id -g` --restart=always -d -p 80:80 -p 443:443 -v $EE_CONF_ROOT/nginx/certs:/etc/nginx/certs -v $EE_CONF_ROOT/nginx/dhparam:/etc/nginx/dhparam -v $EE_CONF_ROOT/nginx/conf.d:/etc/nginx/conf.d -v $EE_CONF_ROOT/nginx/htpasswd:/etc/nginx/htpasswd -v $EE_CONF_ROOT/nginx/vhost.d:/etc/nginx/vhost.d -v /var/run/docker.sock:/tmp/docker.sock:ro -v $EE_CONF_ROOT:/app/ee4 -v /usr/share/nginx/html $nginx_proxy_image" ;

		default_launch( 'docker rm -f ee-nginx-proxy', false, true );

		if ( ! default_launch( $ee_proxy_command, false, true ) ) {
			throw new \Exception( ' Unable to restore ee-nginx-proxy container' );
		}
	}

	public static function cron_container_up() {
		$cron_image = 'easyengine/cron:v' . EE_VERSION;
		$cron_scheduler_run_command = 'docker run --name ee-cron-scheduler --restart=always -d -v ' . EE_CONF_ROOT . '/cron:/etc/ofelia:ro -v /var/run/docker.sock:/var/run/docker.sock:ro ' . $cron_image ;

		default_launch( 'docker rm -f ee-cron-scheduler', false, true );

		if ( ! default_launch( $cron_scheduler_run_command, false, true ) ) {
			throw new \Exception( ' Unable to upgrade ee-cron-scheduler container' );
		}
	}
	public static function cron_container_down( $existing_cron_image ) {
		$cron_image = trim( $existing_cron_image->stdout );
		$cron_scheduler_run_command = 'docker run --name ee-cron-scheduler --restart=always -d -v ' . EE_CONF_ROOT . '/cron:/etc/ofelia:ro -v /var/run/docker.sock:/var/run/docker.sock:ro ' . $cron_image ;

		default_launch( 'docker rm -f ee-cron-scheduler', false, true );

		if ( ! default_launch( $cron_scheduler_run_command, false, true ) ) {
			throw new \Exception( ' Unable to restore ee-cron-scheduler container' );
		}
	}

	/**
	 * Migrates all containers of existing sites
	 */
	private static function migrate_site_containers() {
		$sites       = \EE_DB::select( [ 'sitename', 'site_path', 'site_type', 'cache_type', 'is_ssl', 'db_host' ] );
		$site_docker = new \Site_Docker();

		foreach ( $sites as $site ) {

			$data   = [];
			$data[] = $site['site_type'];
			$data[] = $site['cache_type'];
			$data[] = $site['is_ssl'];
			$data[] = $site['db_host'];

			$docker_compose_contents    = $site_docker->generate_docker_compose_yml( $data );
			$docker_compose_path        = $site['site_path'] . '/docker-compose.yml';
			$docker_compose_backup_path = $site['site_path'] . '/docker-compose.yml.bak';

			self::$rsp->add_step(
				"upgrade-${site['sitename']}-copy-compose-file",
				'EE\Migration\Containers::site_copy_compose_file_up',
				null,
				[ $site, $docker_compose_path, $docker_compose_backup_path ]
			);

			self::$rsp->add_step(
				"upgrade-${site['sitename']}-containers",
				'EE\Migration\Containers::site_containers_up',
				'EE\Migration\Containers::site_containers_down',
				[ $site, $docker_compose_backup_path, $docker_compose_path, $docker_compose_contents ],
				[ $site, $docker_compose_backup_path, $docker_compose_path ]
			);
		}
	}
	public static function site_copy_compose_file_up( $site, $docker_compose_path, $docker_compose_backup_path ) {
		if ( ! default_launch( "cp $docker_compose_path $docker_compose_backup_path" ) ) {
			throw new \Exception( "Unable to find docker-compose.yml in ${site['site_path']} or couldn't create it's backup file. Ensure that EasyEngine has permission to create file there" );
		}
	}
	public static function site_containers_up( $site, $docker_compose_backup_path, $docker_compose_path, $docker_compose_contents ) {
		file_put_contents( $docker_compose_path, $docker_compose_contents );
		$container_upgraded = default_launch( "cd ${site['site_path']} && docker-compose up -d" );

		if ( ! $container_upgraded ) {
			throw new \Exception( "Unable to upgrade containers of site: ${site['sitename']}. Please check logs for more details." );
		}

		unlink( $docker_compose_backup_path );

	}
	public static function site_containers_down( $site, $docker_compose_backup_path, $docker_compose_path ) {
		rename( $docker_compose_backup_path, $docker_compose_path );
		$container_downgraded = default_launch( "cd ${site['site_path']} && docker-compose up -d" );

		if ( ! $container_downgraded ) {
			throw new \Exception( "Unable to downgrade containers of ${site['sitename']} site. Please check logs for more details." );
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
		self::pull_or_error( 'easyengine/postfix', 'v' . EE_VERSION );
		self::pull_or_error( 'easyengine/mailhog', 'v' . EE_VERSION );
		self::pull_or_error( 'easyengine/mariadb', 'v' . EE_VERSION );
		self::pull_or_error( 'easyengine/phpmyadmin', 'v' . EE_VERSION );
		self::pull_or_error( 'easyengine/nginx-proxy', 'v' . EE_VERSION );
	}

	private static function pull_or_error( $image, $version ) {
		if ( ! default_launch( "docker pull $image:$version" ) ) {
			throw new \Exception( "Unable to pull $image. Please check logs for more details." );
		}
	}
}
