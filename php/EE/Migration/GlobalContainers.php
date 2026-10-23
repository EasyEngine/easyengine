<?php

namespace EE\Migration;

use EE;
use Symfony\Component\Filesystem\Filesystem;
use Mustangostang\Spyc;

/**
 * Upgrade existing global containers to new docker-image
 */
class GlobalContainers {

	/**
	 * Upgrade order. The proxy goes last so that a failing backend is rolled back before every site loses its proxy,
	 * and the database just before it, so of the globals only a failing proxy reverts its datadir onto the old image
	 * (a failure in a later site step still reverts every global).
	 */
	const UPGRADE_ORDER = [
		'easyengine/cron',
		'easyengine/newrelic-daemon',
		'easyengine/redis',
		'easyengine/mariadb',
		'easyengine/nginx-proxy',
	];

	/** Seconds a service may take to become ready (`global-service-ready-timeout` in config.yml overrides it). */
	const READY_TIMEOUT = 60;

	/** MariaDB may run crash recovery or a datadir upgrade first. */
	const DB_READY_TIMEOUT = 300;

	/** Seconds a service must stay up without restarting after its first successful probe. */
	const SETTLE_SECONDS = 2;

	/** Services with a readiness probe. The others (cron, newrelic-daemon) only need to have started. */
	const PROBED_SERVICES = [ 'global-nginx-proxy', 'global-db', 'global-redis' ];

	/** @var callable|null Test seam: replaces sleep( $seconds ) while waiting for a service. */
	public static $sleeper;

	/** @var string[] Services this run has recreated, the only ones a rollback must recreate again. */
	private static $recreated = [];

	/**
	 * Sort updated global images into the order their services are upgraded in.
	 *
	 * @param array $images Updated global images.
	 *
	 * @return array
	 */
	public static function sort_for_upgrade( $images ) {
		return array_values( array_merge( array_diff( $images, self::UPGRADE_ORDER ), array_intersect( self::UPGRADE_ORDER, $images ) ) );
	}

	/**
	 * Compose service name of a global container, e.g. `global-db` for `services_global-db_1`.
	 *
	 * @param string $container Container name.
	 *
	 * @return string
	 */
	public static function get_service_name( $container ) {
		return preg_replace( '/^services_(.+)_1$/', '$1', $container );
	}

	/**
	 * Snapshot the global compose file before it is regenerated. A run that was interrupted after its snapshot left the
	 * services half upgraded, so its snapshot (marked pending until a run completes) is kept instead of the current file.
	 *
	 * @param string $source Global compose file.
	 * @param string $backup Snapshot path.
	 */
	public static function backup_compose_file( $source, $backup ) {
		$fs = new Filesystem();
		if ( $fs->exists( [ $backup, $backup . '.pending' ] ) ) {
			EE::debug( "Keeping $backup from an upgrade that did not complete" );

			return;
		}
		$fs->copy( $source, $backup, true );
		$fs->touch( $backup . '.pending' );
	}

	/**
	 * Mark the compose snapshot as no longer needed by a later run: the upgrade completed or was rolled back.
	 *
	 * @param string $backup Snapshot path.
	 */
	public static function clear_pending_compose_backup( $backup ) {
		( new Filesystem() )->remove( $backup . '.pending' );
	}

	/**
	 * Start a new image migration with no service recorded as recreated.
	 */
	public static function reset_recreated() {
		self::$recreated = [];
	}

	/**
	 * Refuse to upgrade when the running proxy's config fails `nginx -t`: it lives on volumes, so the recreated proxy and
	 * the restored one would both fail to start. Runs before any global service is recreated.
	 *
	 * @param string $container Proxy container name.
	 *
	 * @throws \Exception
	 */
	public static function check_running_proxy_config( $container ) {
		if ( 'running' === self::get_container_state( $container )['status'] && ! EE::exec( 'docker exec ' . escapeshellarg( $container ) . ' nginx -t' ) ) {
			throw new \Exception( "The configuration of the running $container fails `nginx -t`, so a recreated proxy would not start. Fix it (see `docker exec $container nginx -t`) and run the upgrade again." );
		}
	}

	/**
	 * Recreate one global service with the image in the (already regenerated) global compose file,
	 * then wait until it is ready. Throws on failure, so the image migration rolls back.
	 *
	 * @param string $service   Compose service, e.g. `global-db`.
	 * @param string $container Its container name.
	 *
	 * @throws \Exception
	 */
	public static function upgrade_global_service( $service, $container ) {
		EE::debug( "Start upgrading $container" );

		self::$recreated[] = $service;
		if ( ! chdir( EE_SERVICE_DIR ) || ! self::compose_up( $service, [ $service ] ) ) {
			throw new \Exception( "Unable to recreate $container with its new image. Please check logs for more details." );
		}

		self::wait_until_ready( $service, $container );
		EE::debug( "Complete upgrading $container" );
	}

	/**
	 * `up -d` global services in the current directory. docker-compose v1 can't recreate a container on Docker 25+
	 * (KeyError: 'ContainerConfig', leaving it stopped), so with v1 the services to recreate are stopped and removed first.
	 *
	 * @param string   $services Space-separated compose services.
	 * @param string[] $recreate Those whose container must be replaced; v1's `up -d` leaves the others alone.
	 *
	 * @return bool
	 */
	private static function compose_up( $services, $recreate ) {
		$compose = \EE_DOCKER::docker_compose_with_custom();
		$up      = "$compose up -d $services";
		$version = Containers::get_docker_compose_version();
		if ( $recreate && '' !== $version && version_compare( $version, '2.0.0', '<' ) ) {
			$recreate = implode( ' ', $recreate );

			return EE::exec( "$compose stop $recreate && $compose rm -f $recreate && $up" );
		}

		return EE::exec( $up );
	}

	/**
	 * Wait until a global container runs, isn't restarting, is healthy if it has a healthcheck,
	 * and passes its service's readiness probe, then stays so for a moment. Unprobed services only need to have started.
	 *
	 * @param string   $service   Compose service, e.g. `global-db`.
	 * @param string   $container Its container name.
	 * @param int|null $timeout   Seconds; defaults to the service's timeout.
	 *
	 * @throws \Exception When it isn't ready in time or its healthcheck reports unhealthy.
	 */
	public static function wait_until_ready( $service, $container, $timeout = null ) {
		if ( null === $timeout ) {
			$timeout = self::get_ready_timeout( $service );
		}
		$start  = microtime( true );
		$slept  = 0;
		$reason = '';

		while ( true ) {
			$state = self::get_container_state( $container );
			if ( 'unhealthy' === $state['health'] ) {
				throw new \Exception( "$container is unhealthy according to its healthcheck." );
			}

			// No readiness signal, and cron restart-loops while it has no jobs (CRON-1): having started is enough.
			if ( ! in_array( $service, self::PROBED_SERVICES, true ) && in_array( $state['status'], [ 'running', 'restarting' ], true ) && in_array( $state['health'], [ 'healthy', 'none' ], true ) ) {
				if ( 'running' !== $state['status'] || 'false' !== $state['restarting'] ) {
					EE::warning( "$container keeps restarting. Please check `docker logs $container`." );
				}

				return;
			}

			$reason = self::not_ready_reason( $service, $container, $state );
			if ( '' === $reason ) {
				self::pause( self::SETTLE_SECONDS );
				$slept  += self::SETTLE_SECONDS;
				$settled = self::get_container_state( $container );
				$reason  = $settled['restart_count'] !== $state['restart_count'] ? 'it restarted' : self::not_ready_reason( $service, $container, $settled );
				if ( '' === $reason ) {
					EE::debug( sprintf( '%s is ready after %.1f s', $container, microtime( true ) - $start ) );

					return;
				}
			}

			// Time slept counts too, so a test sleeper that returns at once still ends the wait.
			if ( max( microtime( true ) - $start, $slept ) >= $timeout ) {
				throw new \Exception( sprintf( '%s is not ready after %d s (%s). Please check `docker logs %s`.', $container, $timeout, $reason, $container ) );
			}
			self::pause( 1 );
			++$slept;
		}
	}

	/**
	 * @param string $service Compose service.
	 *
	 * @return int Seconds.
	 */
	private static function get_ready_timeout( $service ) {
		$configured = \EE\Utils\get_config_value( 'global-service-ready-timeout' );
		if ( is_numeric( $configured ) && $configured > 0 ) {
			return (int) ceil( $configured );
		}

		return 'global-db' === $service ? self::DB_READY_TIMEOUT : self::READY_TIMEOUT;
	}

	/**
	 * @param string $container Container name.
	 *
	 * @return array status, restarting, restart_count and health (`none` without a healthcheck); empty strings if it doesn't exist.
	 */
	private static function get_container_state( $container ) {
		$format = '{{.State.Status}} {{.State.Restarting}} {{.RestartCount}} {{if .State.Health}}{{.State.Health.Status}}{{else}}none{{end}}';
		$result = EE::launch( sprintf( "docker inspect --format '%s' %s", $format, escapeshellarg( $container ) ) );
		$parts  = 0 === $result->return_code ? preg_split( '/\s+/', trim( $result->stdout ) ) : [];

		return array_combine( [ 'status', 'restarting', 'restart_count', 'health' ], array_pad( array_slice( $parts, 0, 4 ), 4, '' ) );
	}

	/**
	 * @param string $service   Compose service.
	 * @param string $container Container name.
	 * @param array  $state     From get_container_state().
	 *
	 * @return string Why it isn't ready, or '' when it is.
	 */
	private static function not_ready_reason( $service, $container, $state ) {
		if ( '' === $state['status'] ) {
			return 'no such container';
		}
		if ( 'running' !== $state['status'] || 'false' !== $state['restarting'] ) {
			return "status {$state['status']}";
		}
		if ( ! in_array( $state['health'], [ 'healthy', 'none' ], true ) ) {
			return "health {$state['health']}";
		}

		return self::probe( $service, $container );
	}

	/**
	 * Service-specific readiness probe.
	 *
	 * @param string $service   Compose service.
	 * @param string $container Container name.
	 *
	 * @return string Why it failed, or '' when it passed.
	 */
	private static function probe( $service, $container ) {
		$exec = 'docker exec ' . escapeshellarg( $container ) . ' ';

		switch ( $service ) {
			case 'global-nginx-proxy':
				if ( ! EE::exec( $exec . 'nginx -t' ) ) {
					return 'nginx -t failed';
				}
				// Any HTTP status proves nginx answers through the published port; 443 is the same process.
				$port   = (string) \EE\Utils\get_config_value( 'proxy_80_port', '80' );
				$host   = false === strpos( $port, ':' ) ? '127.0.0.1' : substr( $port, 0, strrpos( $port, ':' ) );
				$port   = false === strpos( $port, ':' ) ? $port : substr( $port, strrpos( $port, ':' ) + 1 );
				// --noproxy: an http_proxy exported for pulls must not answer for the local proxy.
				$status = trim( EE::launch( sprintf( "curl -s --noproxy '*' -o /dev/null -w '%%{http_code}' --max-time 5 %s", escapeshellarg( "http://$host:$port/" ) ) )->stdout );

				return preg_match( '/^[1-5]\d\d$/', $status ) ? '' : "no HTTP response on port $port";

			case 'global-db':
				// Over TCP: the entrypoint's temporary init/upgrade server listens on the socket only. Access denied still means alive.
				$ping = 'for c in mariadb-admin mysqladmin; do command -v $c >/dev/null 2>&1 && exec $c ping -h 127.0.0.1 --protocol=tcp --connect-timeout=2 --silent; done; exit 1';

				return EE::exec( $exec . 'sh -c ' . escapeshellarg( $ping ) ) ? '' : 'mysqladmin ping failed';

			case 'global-redis':
				$pong = trim( EE::launch( $exec . 'redis-cli ping' )->stdout );

				// NOAUTH (a requirepass in redis.conf) still means redis answers.
				return 'PONG' === $pong || 0 === strpos( $pong, 'NOAUTH' ) ? '' : 'redis-cli ping failed';
		}

		return '';
	}

	/**
	 * @param int|float $seconds Seconds to sleep.
	 */
	private static function pause( $seconds ) {
		if ( self::$sleeper ) {
			call_user_func( self::$sleeper, $seconds );

			return;
		}
		usleep( (int) ( $seconds * 1000000 ) );
	}

	/**
	 * Get global containers which has new image update.
	 *
	 * @param $updated_images array of updated docker-images
	 *
	 * @return array
	 */
	public static function get_updated_global_images( $updated_images ) {

		$global_images           = self::get_all_global_images_with_service_name();
		$running_global_services = [];
		foreach ( $global_images as $image => $container_name ) {
			if ( 'running' === \EE_DOCKER::container_status( $container_name ) ) {
				$running_global_services[] = $image;
			}
		}

		return array_intersect( $running_global_services, $updated_images );
	}

	/**
	 * * Restore  backed up docker-compose.yml file.
	 *
	 * @param $source_path string path of backup file.
	 * @param $dest_path   string path of global docker-compose.yml
	 *
	 * @throws \Exception
	 */
	public static function revert_global_containers( $source_path, $dest_path, $updated_images ) {

		$all_global_images = self::get_all_global_images_with_service_name();
		$services          = [];
		foreach ( $updated_images as $image_name ) {
			$services[] = self::get_service_name( $all_global_images[ $image_name ] );
		}
		$recreated       = self::$recreated;
		self::$recreated = [];
		if ( empty( $services ) ) {
			return;
		}
		EE::debug( 'Start restoring global docker-compose.yml file from backup' );
		$fs = new Filesystem();
		$fs->copy( $source_path, $dest_path, true );

		// Services an earlier, interrupted attempt moved to a new image aren't among this run's updates: put them back too.
		$moved    = array_diff( self::get_services_off_compose_file( $dest_path ), $services );
		$services = array_merge( $services, $moved );
		$recreate = array_values( array_merge( array_intersect( $services, $recreated ), $moved ) );

		chdir( EE_ROOT_DIR . '/services' );

		if ( ! self::compose_up( implode( ' ', $services ), $recreate ) ) {
			throw new \Exception( 'Unable to downgrade global containers. Please check logs for more details.' );
		}
		$containers = array_combine( array_map( [ self::class, 'get_service_name' ], $all_global_images ), $all_global_images );
		foreach ( $services as $service ) {
			try {
				self::wait_until_ready( $service, $containers[ $service ] );
			} catch ( \Exception $e ) {
				EE::warning( 'After restoring the previous image: ' . $e->getMessage() );
			}
		}
		self::clear_pending_compose_backup( $source_path );
		EE::debug( 'Complete restoring global docker-compose.yml file from backup' );
	}

	/**
	 * @param string $compose_file Global compose file.
	 *
	 * @return string[] Global services that run an image other than the one the compose file sets for them.
	 */
	private static function get_services_off_compose_file( $compose_file ) {
		// Spyc, not symfony/yaml: the phar doesn't ship symfony/yaml.
		$config   = is_file( $compose_file ) ? Spyc::YAMLLoad( $compose_file ) : [];
		$services = [];
		foreach ( self::get_all_global_images_with_service_name() as $container ) {
			$service = self::get_service_name( $container );
			$image   = is_array( $config ) && isset( $config['services'][ $service ]['image'] ) ? $config['services'][ $service ]['image'] : '';
			$running = EE::launch( sprintf( "docker inspect --format '{{.State.Status}} {{.Config.Image}}' %s", escapeshellarg( $container ) ) );
			$parts   = 0 === $running->return_code ? preg_split( '/\s+/', trim( $running->stdout ) ) : [];
			if ( '' !== $image && 2 === count( $parts ) && 'running' === $parts[0] && $parts[1] !== $image ) {
				$services[] = $service;
			}
		}

		return $services;
	}

	/**
	 * Stop global container and remove them.
	 *
	 * @param $updated_images array of newly available images.
	 *
	 * @throws \Exception
	 */
	public static function down_global_containers( $updated_images ) {
		EE::debug( 'Start removing global containers' );
		chdir( EE_ROOT_DIR . '/services' );
		$all_global_images = self::get_all_global_images_with_service_name();

		foreach ( $updated_images as $image_name ) {
			$global_container_name = $all_global_images[ $image_name ];
			$global_service_name   = ltrim( $global_container_name, 'services_' );
			$remove_suffix         = explode( '_1', $global_service_name );
			$global_service_name   = empty( $remove_suffix[0] ) ? $global_service_name : $remove_suffix[0];
			EE::debug( "Removing $global_container_name" );

			if ( false !== \EE_DOCKER::container_status( $global_container_name ) ) {
				if ( ! EE::exec( \EE_DOCKER::docker_compose_with_custom() . " stop $global_service_name && ". \EE_DOCKER::docker_compose_with_custom() . " rm -f $global_service_name" ) ) {
					throw new \Exception( "Unable to stop $global_container_name container" );
				}
			}
		}
		EE::debug( 'Complete removing global containers' );
	}

	/**
	 * Upgrade global service container.
	 *
	 * @throws \Exception
	 */
	public static function global_service_up( $service_name ) {
		$global_service_name = ltrim( $service_name, 'services_' );
		$remove_suffix       = explode( '_1', $global_service_name );
		$global_service_name = empty( $remove_suffix[0] ) ? $global_service_name : $remove_suffix[0];
		EE::debug( 'Start ' . $service_name . ' container up' );
		if ( 'global-nginx-proxy' === $global_service_name ) {
			\EE\Service\Utils\nginx_proxy_check();
		} else {
			\EE\Service\Utils\init_global_container( $service_name );
		}
	}

	/**
	 * Remove upgraded global service container.
	 *
	 * @throws \Exception
	 */
	public static function global_service_down( $service_name ) {
		EE::debug( 'Start ' . $service_name . ' container removing' );
		chdir( EE_ROOT_DIR . '/services' );

		if ( ! EE::exec( \EE_DOCKER::docker_compose_with_custom() . " stop $service_name && ". \EE_DOCKER::docker_compose_with_custom() . " rm -f $service_name" ) ) {
			throw new \Exception( sprintf( 'Unable to remove %1$s container', $service_name ) );
		}
		EE::debug( 'Complete ' . $service_name . ' container removing' );
	}


	/**
	 * Get all global images with it's service name.
	 *
	 * @return array
	 */
	public static function get_all_global_images_with_service_name() {

		return [
			'easyengine/nginx-proxy'     => GLOBAL_PROXY_CONTAINER,
			'easyengine/mariadb'         => GLOBAL_DB_CONTAINER,
			'easyengine/redis'           => GLOBAL_REDIS_CONTAINER,
			'easyengine/cron'            => GLOBAL_CRON_CONTAINER,
			'easyengine/newrelic-daemon' => GLOBAL_NEWRELIC_DAEMON_CONTAINER,
		];
	}
}
