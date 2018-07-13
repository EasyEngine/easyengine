<?php

use function \EE\Utils\default_debug;
use function \EE\Utils\default_launch;
use function \EE\Utils\mustache_render;

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
			return self::create_container( $container, $command );
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
		$status = EE::launch( "docker inspect -f '{{.State.Running}}' $container", false, true );
		default_debug( $status );
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
		return default_launch( "docker start $container" );
	}

	/**
	 * Function to create and start the container if it does not exist.
	 *
	 * @param String $container Container to be created.
	 * @param String $command   Command to launch the container.
	 *
	 * @return bool success.
	 */
	public static function create_container( $container, $command ) {

		$launch = EE::launch( $command, false, true );
		default_debug( $launch );
		if ( ! $launch->return_code ) {
			return true;
		}
		EE::error( $launch->stderr );
	}

	/**
	 * Create docker network.
	 *
	 * @param String $name Name of the network to be created.
	 *
	 * @return bool success.
	 */
	public static function create_network( $name ) {
		return default_launch( "docker network create $name" );
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
		return default_launch( "docker network connect $name $connect_to" );
	}

	/**
	 * Remove docker network.
	 *
	 * @param String $name Name of the network to be removed.
	 *
	 * @return bool success.
	 */
	public static function rm_network( $name ) {
		return default_launch( "docker network rm $name" );
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
		return default_launch( "docker network disconnect $name $connected_to" );
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
				return default_launch( 'docker-compose up -d' );
			} else {
				$all_services = implode( ' ', $services );

				return default_launch( "docker-compose up -d $all_services" );
			}
		}

		return false;
	}

	/**
	 * Function to destroy the containers.
	 *
	 * @param String $dir      Path to docker-compose.yml.
	 * @param array  $services Services to bring up.
	 *
	 * @return bool success.
	 */
	public static function docker_compose_down( $dir, $services = [] ) {
		$chdir_return_code = chdir( $dir );
		if ( $chdir_return_code ) {
			if ( empty( $services ) ) {
				return default_launch( 'docker-compose up -d' );
			} else {
				$all_services = implode( ' ', $services );

				return default_launch( "docker-compose up $all_services -d" );
			}
		}

		return false;
	}
}
