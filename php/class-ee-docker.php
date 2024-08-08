<?php

use Symfony\Component\Filesystem\Filesystem;

class EE_DOCKER {

	/**
	 * Function to return docker-compose command with custom docker-compose files
	 *
	 * @param array $files_before_custom Files to be included before custom compose file is included
	 * @param bool  $get_service         Boolean to check if it is for getting service call or not.
	 *
	 * @return string
	 */
	public static function docker_compose_with_custom( array $files_before_custom = [], bool $get_service = false ): string {

		$fs = new Filesystem();

		if ( $get_service ) {
			$command = 'docker-compose ';
		} else {
			$command = 'docker-compose -f docker-compose.yml ';
		}

		$custom_compose = \EE::get_runner()->config['custom-compose'];

		if ( ! empty( $custom_compose ) ) {
			$custom_compose_path = SITE_CUSTOM_DOCKER_COMPOSE_DIR . '/' . $custom_compose;
			if ( SITE_CUSTOM_DOCKER_COMPOSE === $custom_compose ) {
				if ( $fs->exists( SITE_CUSTOM_DOCKER_COMPOSE ) ) {
					$custom_compose_path = SITE_CUSTOM_DOCKER_COMPOSE;
				}
			}
			if ( $fs->exists( $custom_compose_path ) ) {
				$command .= ' -f ' . $custom_compose_path;
			} else {
				EE::warning( 'File: ' . $custom_compose_path . ' does not exist. Falling back to default compose file.' );
			}
		} else {

			if ( $fs->exists( SITE_CUSTOM_DOCKER_COMPOSE_DIR ) ) {
				$ymlFiles  = glob( SITE_CUSTOM_DOCKER_COMPOSE_DIR . '/*.yml' );
				$yamlFiles = glob( SITE_CUSTOM_DOCKER_COMPOSE_DIR . '/*.yaml' );

				$custom_compose_files = array_merge( $ymlFiles, $yamlFiles );

				$files_before_custom = array_unique( array_merge( $files_before_custom, $custom_compose_files ) );
			}

			foreach ( $files_before_custom as $file ) {
				if ( $fs->exists( $file ) ) {
					$command .= ' -f ' . $file;
				}
			}

			if ( $fs->exists( SITE_CUSTOM_DOCKER_COMPOSE ) ) {
				$command .= ' -f ' . SITE_CUSTOM_DOCKER_COMPOSE;
			}
		}

		return $command;
	}

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
				return EE::exec( \EE_DOCKER::docker_compose_with_custom() . ' up -d' );
			} else {
				$all_services = implode( ' ', $services );

				return EE::exec( \EE_DOCKER::docker_compose_with_custom() . ' up -d ' . $all_services );
			}
		}

		return false;
	}

	/**
	 * Function to exec and run commands into the containers.
	 *
	 * @param string $command        Command to exec.
	 * @param string $service        Service to exec command into.
	 * @param string $shell          Shell in which exec command will be executed.
	 * @param string $user           User to execute command into.
	 * @param String $dir            Path to docker-compose.yml.
	 * @param bool   $shell_wrapper  If shell wrapper should be enabled or not.
	 * @param bool   $exit_on_error  To exit or not on error.
	 * @param array  $exec_obfuscate Data to be obfuscated from log.
	 * @param bool   $echo_stdout    Output stdout of exec if true.
	 * @param bool   $echo_stderr    Output stderr of exec if true.
	 *
	 * @return bool success.
	 */
	public static function docker_compose_exec( $command = '', $service = '', $shell = 'sh', $user = '', $dir = '', $shell_wrapper = false, $exit_on_error = false, $exec_obfuscate = [], $echo_stdout = false, $echo_stderr = false ) {

		if ( ! empty( $dir ) ) {
			$chdir_return_code = chdir( $dir );
		} else {
			$chdir_return_code = true;
		}

		$skip_tty = \EE::get_runner()->config['skip-tty'];
		$tty      = empty( $skip_tty ) ? '' : '-T';

		if ( $chdir_return_code ) {

			$user_string = '';
			if ( $user ) {
				$user_string = empty( $user ) ? '' : "--user='$user'";
			}

			if ( $shell_wrapper ) {
				return EE::exec( \EE_DOCKER::docker_compose_with_custom() . " exec $tty $user_string $service $shell -c \"$command\"", $echo_stdout, $echo_stderr, $exec_obfuscate, $exit_on_error );
			} else {
				return EE::exec( \EE_DOCKER::docker_compose_with_custom() . " exec $tty $user_string $service $command", $echo_stdout, $echo_stderr, $exec_obfuscate, $exit_on_error );
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
	 * @param String $dir Path to docker-compose.yml.
	 *
	 * @return bool success.
	 */
	public static function docker_compose_down( $dir ) {

		$chdir_return_code = chdir( $dir );
		if ( $chdir_return_code ) {

			return EE::exec( \EE_DOCKER::docker_compose_with_custom() . ' down' );
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

		return in_array( $service, \EE_DOCKER::get_services( $site_fs_path ), true );
	}

	/**
	 * Get list of all docker-compose services.
	 *
	 * @param string $site_fs_path Path to the site root where docker-compose.yml file is present.
	 *
	 * @return bool Whether service is available or not.
	 */
	public static function get_services( $site_fs_path = '' ) {

		if ( ! empty( $site_fs_path ) ) {
			chdir( $site_fs_path );
		}
		$custom_service = empty( \EE::get_runner()->config['custom-compose'] ) ? false : true;
		$launch         = EE::launch( \EE_DOCKER::docker_compose_with_custom( [], $custom_service ) . ' config --services' );
		$services       = explode( PHP_EOL, trim( $launch->stdout ) );

		return $services;
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
	 * @param string $prefix                  Prefix by volumes have to be created.
	 * @param array  $volumes                 The volumes to be created.
	 *                                        $volumes[$key]['name'] => specifies the name of volume to be created.
	 *                                        $volumes[$key]['path_to_symlink'] => specifies the path to symlink the
	 *                                        created volume.
	 *                                        $volumes[$key]['skip_volume'] => if set to `true` will skip volume
	 *                                        creation for that entry.
	 * @param bool   $update_to_docker_prefix Update the prefix in dockerized style.
	 */
	public static function create_volumes( $prefix, $volumes, $update_to_docker_prefix = true ) {

		$volume_prefix = $update_to_docker_prefix ? self::get_docker_style_prefix( $prefix ) : $prefix;
		$fs            = new Filesystem();

		// This command will get the root directory for Docker, generally `/var/lib/docker`.
		$launch          = EE::launch( "docker info 2> /dev/null | awk '/Docker Root Dir/ {print $4}'" );
		$docker_root_dir = trim( $launch->stdout );

		foreach ( $volumes as $volume ) {
			if ( ! empty( $volume['skip_volume'] ) && true === $volume['skip_volume'] ) {
				continue;
			}
			$vol_check = EE::launch( 'docker volume inspect ' . $volume_prefix . '_' . $volume['name'] );
			// Skip if volume already exists.
			if ( 0 === $vol_check->return_code ) {
				continue;
			}
			$path_to_symlink_not_empty = ! empty( dirname( $volume['path_to_symlink'] ) );
			if ( $path_to_symlink_not_empty ) {
				$fs->mkdir( dirname( $volume['path_to_symlink'] ) );
			}
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
			if ( $path_to_symlink_not_empty ) {
				$fs->symlink( sprintf( '%s/volumes/%s_%s/_data', $docker_root_dir, $volume_prefix, $volume['name'] ), $volume['path_to_symlink'] );
			}
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

	/**
	 * Function to return minimal docker-compose `host:container` volume mounting array.
	 *
	 * @param array $extended_vols :
	 *                             $extended_vols['name'] - Host path for docker-compose generation in linux
	 *                             $extended_vols['path_to_symlink'] - Host path for docker-compose generation in
	 *                             darwin.
	 *                             $extended_vols['container_path'] - Path inside container, common for linux and
	 *                             darwin.
	 *                             $extended_vols['skip_darwin'] - if set to true skips that volume for darwin.
	 *                             $extended_vols['skip_linux'] - if set to true skips that volume for linux.
	 *
	 * @return array having docker-compose `host:container` volume mounting.
	 */
	public static function get_mounting_volume_array( $extended_vols ) {

		$volume_gen_key      = IS_DARWIN ? 'path_to_symlink' : 'name';
		$skip_key            = IS_DARWIN ? 'skip_darwin' : 'skip_linux';
		$final_mount_volumes = [];
		foreach ( $extended_vols as $extended_vol ) {
			if ( ! empty( $extended_vol[ $skip_key ] ) && true === $extended_vol[ $skip_key ] ) {
				continue;
			}
			$final_mount_volumes[] = $extended_vol[ $volume_gen_key ] . ':' . $extended_vol['container_path'];
		}

		return $final_mount_volumes;
	}

}
