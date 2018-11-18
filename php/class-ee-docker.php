<?php

use Symfony\Component\Filesystem\Filesystem;

class EE_DOCKER {

	/**
	 * Check and Start or create container if not running.
	 *
	 * @param String $container Name of the container.
	 * @param String $command   Command to launch that container if needed.
	 *
	 * @return bool success.
	 */
	public static function boot_container( $container, $command = '' ) {
		$status = self::container_status( $container );
		if ( $status ) {
			if ( 'exited' === $status ) {
				return self::start_container( $container );
			} else {
				return true;
			}
		} else {
			return EE::exec( $command );
		}
	}

	public static function container_status( $container ) {
		$exec_command = 'which docker';
		exec( $exec_command, $out, $ret );
		EE::debug( 'COMMAND: ' . $exec_command );
		EE::debug( 'RETURN CODE: ' . $ret );
		if ( $ret ) {
			EE::error( 'Docker is not installed. Please install Docker to run EasyEngine.' );
		}
		$status = EE::launch( "docker inspect -f '{{.State.Running}}' $container" );
		if ( ! $status->return_code ) {
			if ( preg_match( '/true/', $status->stdout ) ) {
				return 'running';
			} else {
				return 'exited';
			}
		}

		return false;
	}

	/**
	 * Function to start the container if it exists but is not running.
	 *
	 * @param String $container Container to be started.
	 *
	 * @return bool success.
	 */
	public static function start_container( $container ) {
		return EE::exec( "docker start $container" );
	}

	/**
	 * Function to stop a container
	 *
	 * @param String $container Container to be stopped.
	 *
	 * @return bool success.
	 */
	public static function stop_container( $container ) {
		return EE::exec( "docker stop $container" );
	}

	/**
	 * Function to restart a container
	 *
	 * @param String $container Container to be restarted.
	 *
	 * @return bool success.
	 */
	public static function restart_container( $container ) {
		return EE::exec( "docker restart $container" );
	}

	/**
	 * Create docker network.
	 *
	 * @param String $name Name of the network to be created.
	 *
	 * @return bool success.
	 */
	public static function create_network( $name ) {
		return EE::exec( "docker network create $name --label=org.label-schema.vendor=\"EasyEngine\" " );
	}

	/**
	 * Connect to given docker network.
	 *
	 * @param String $name       Name of the network that has to be connected.
	 * @param String $connect_to Name of the network to which connection has to be established.
	 *
	 * @return bool success.
	 */
	public static function connect_network( $name, $connect_to ) {
		return EE::exec( "docker network connect $name $connect_to" );
	}

	/**
	 * Remove docker network.
	 *
	 * @param String $name Name of the network to be removed.
	 *
	 * @return bool success.
	 */
	public static function rm_network( $name ) {
		return EE::exec( "docker network rm $name" );
	}

	/**
	 * Disconnect docker network.
	 *
	 * @param String $name         Name of the network to be disconnected.
	 * @param String $connected_to Name of the network from which it has to be disconnected.
	 *
	 * @return bool success.
	 */
	public static function disconnect_network( $name, $connected_to ) {
		return EE::exec( "docker network disconnect $name $connected_to" );
	}


	/**
	 * Function to connect site network to appropriate containers.
	 */
	public static function connect_site_network_to( $site_name, $to_container ) {

		if ( self::connect_network( $site_name, $to_container ) ) {
			EE::success( "Site connected to $to_container." );
		} else {
			throw new Exception( "There was some error connecting to $to_container." );
		}
	}

	/**
	 * Function to disconnect site network from appropriate containers.
	 */
	public static function disconnect_site_network_from( $site_name, $from_container ) {

		if ( self::disconnect_network( $site_name, $from_container ) ) {
			EE::log( "[$site_name] Disconnected from Docker network of $from_container" );
		} else {
			EE::warning( "Error in disconnecting from Docker network of $from_container" );
		}
	}


	/**
	 * Function to boot the containers.
	 *
	 * @param String $dir      Path to docker-compose.yml.
	 * @param array  $services Services to bring up.
	 *
	 * @return bool success.
	 */
	public static function docker_compose_up( $dir, $services = [] ) {
		$chdir_return_code = chdir( $dir );
		if ( $chdir_return_code ) {
			if ( empty( $services ) ) {
				return EE::exec( 'docker-compose up -d' );
			} else {
				$all_services = implode( ' ', $services );

				return EE::exec( "docker-compose up -d $all_services" );
			}
		}

		return false;
	}

	/**
	 * Function to check if a network exists
	 *
	 * @param string $network Name/ID of network to check
	 *
	 * @return bool Network exists or not
	 */
	public static function docker_network_exists( string $network ) {
		return EE::exec( "docker network inspect $network" );
	}

	/**
	 * Function to destroy the containers.
	 *
	 * @param String $dir      Path to docker-compose.yml.
	 *
	 * @return bool success.
	 */
	public static function docker_compose_down( $dir ) {
		$chdir_return_code = chdir( $dir );
		if ( $chdir_return_code ) {

			return EE::exec( 'docker-compose down' );
		}

		return false;
	}

	/**
	 * Check if a particular service exists in given docker-compose.yml.
	 *
	 * @param string $service      Service whose availability needs to be checked.
	 * @param string $site_fs_path Path to the site root where docker-compose.yml file is present.
	 *
	 * @return bool Whether service is available or not.
	 */
	public static function service_exists( $service, $site_fs_path ) {
		chdir( $site_fs_path );
		$launch   = EE::launch( 'docker-compose config --services' );
		$services = explode( PHP_EOL, trim( $launch->stdout ) );

		return in_array( $service, $services, true );
	}

	/**
	 * Gets a dockerized prefix created for site.
	 *
	 * @param string $site_url Name of the site.
	 *
	 * @return string prefix derived from the name.
	 */
	public static function get_docker_style_prefix( $site_url ) {
		return str_replace( [ '.', '-' ], '', $site_url );
	}

	/**
	 * Function to create external docker volumes and related symlinks.
	 *
	 * @param string $prefix                Prefix by volumes have to be created.
	 * @param array $volumes                The volumes to be created.
	 *                                      $volumes[$key]['name'] => specifies the name of volume to be created.
	 *                                      $volumes[$key]['path_to_symlink'] => specifies the path to symlink the created volume.
	 * @param bool $update_to_docker_prefix Update the prefix in dockerized style.
	 */
	public static function create_volumes( $prefix, $volumes, $update_to_docker_prefix = true ) {

		$volume_prefix = $update_to_docker_prefix ? self::get_docker_style_prefix( $prefix ) : $prefix;
		$fs            = new Filesystem();

		// This command will get the root directory for Docker, generally `/var/lib/docker`.
		$launch          = EE::launch( "docker info 2> /dev/null | awk '/Docker Root Dir/ {print $4}'" );
		$docker_root_dir = trim( $launch->stdout );

		foreach ( $volumes as $volume ) {
			$fs->mkdir( dirname( $volume['path_to_symlink'] ) );
			EE::exec(
				sprintf(
					'docker volume create \
					--label "org.label-schema.vendor=EasyEngine" \
					--label "io.easyengine.site=%s" \
					%s_%s',
					$prefix,
					$volume_prefix,
					$volume['name']
				)
			);
			$fs->symlink( sprintf( '%s/volumes/%s_%s/_data', $docker_root_dir, $volume_prefix, $volume['name'] ), $volume['path_to_symlink'] );
		}
	}

	/**
	 * Function to get all the volumes with a specific label.
	 *
	 * @param string $label The label to search for.
	 *
	 * @return array Found containers.
	 */
	public static function get_volumes_by_label( $label ) {
		$launch = EE::launch( sprintf( 'docker volume ls --filter="label=org.label-schema.vendor=EasyEngine" --filter="label=io.easyengine.site=%s" -q', $label ) );

		return array_filter( explode( PHP_EOL, trim( $launch->stdout ) ), 'trim' );
	}
}
