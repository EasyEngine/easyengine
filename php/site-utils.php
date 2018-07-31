<?php

namespace EE\SiteUtils;

use \EE;
use \Symfony\Component\Filesystem\Filesystem;

/**
 * Get the site-name from the path from where ee is running if it is a valid site path.
 *
 * @return bool|String Name of the site or false in failure.
 */
function get_site_name() {
	$sites = EE::db()::select( array( 'sitename' ) );

	if ( $sites ) {
		$cwd          = getcwd();
		$name_in_path = explode( '/', $cwd );
		$site_name    = array_intersect( EE\Utils\array_flatten( $sites ), $name_in_path );

		if ( 1 === count( $site_name ) ) {
			$name = reset( $site_name );
			$path = EE::db()::select( array( 'site_path' ), array( 'sitename' => $name ) );
			if ( $path ) {
				$site_path = $path[0]['site_path'];
				if ( $site_path === substr( $cwd, 0, strlen( $site_path ) ) ) {
					return $name;
				}
			}
		}
	}

	return false;
}

/**
 * Function to set the site-name in the args when ee is running in a site folder and the site-name has not been passed in the args. If the site-name could not be found it will throw an error.
 *
 * @param array   $args     The passed arguments.
 * @param String  $command  The command passing the arguments to auto-detect site-name.
 * @param String  $function The function passing the arguments to auto-detect site-name.
 * @param integer $arg_pos  Argument position where Site-name will be present.
 *
 * @return array Arguments with site-name set.
 */
function auto_site_name( $args, $command, $function, $arg_pos = 0 ) {
	if ( isset( $args[$arg_pos] ) ) {
		if ( EE::db()::site_in_db( $args[$arg_pos] ) ) {
			return $args;
		}
	}
	$site_name = get_site_name();
	if ( $site_name ) {
		if ( isset( $args[$arg_pos] ) ) {
			EE::error( $args[$arg_pos] . " is not a valid site-name. Did you mean `ee $command $function $site_name`?" );
		}
		array_splice( $args, $arg_pos, 0, $site_name );
	} else {
		EE::error( "Could not find the site you wish to run $command $function command on.\nEither pass it as an argument: `ee $command $function <site-name>` \nor run `ee $command $function` from inside the site folder." );
	}

	return $args;
}


/**
 * Function to check all the required configurations needed to create the site.
 *
 * Boots up the container if it is stopped or not running.
 */
function init_checks() {
	$proxy_type = EE_PROXY_TYPE;
	if ( 'running' !== EE::docker()::container_status( $proxy_type ) ) {
		/**
		 * Checking ports.
		 */
		$port_80_status  = get_curl_info( 'localhost', 80, true );
		$port_443_status = get_curl_info( 'localhost', 443, true );

		// if any/both the port/s is/are occupied.
		if ( ! ( $port_80_status && $port_443_status ) ) {
			EE::error( 'Cannot create/start proxy container. Please make sure port 80 and 443 are free.' );
		} else {
			$EE_CONF_ROOT     = EE_CONF_ROOT;
			$ee_proxy_command = "docker run --name $proxy_type -e LOCAL_USER_ID=`id -u` -e LOCAL_GROUP_ID=`id -g` --restart=always -d -p 80:80 -p 443:443 -v $EE_CONF_ROOT/nginx/certs:/etc/nginx/certs -v $EE_CONF_ROOT/nginx/dhparam:/etc/nginx/dhparam -v $EE_CONF_ROOT/nginx/conf.d:/etc/nginx/conf.d -v $EE_CONF_ROOT/nginx/htpasswd:/etc/nginx/htpasswd -v $EE_CONF_ROOT/nginx/vhost.d:/etc/nginx/vhost.d -v /var/run/docker.sock:/tmp/docker.sock:ro -v $EE_CONF_ROOT:/app/ee4 -v /usr/share/nginx/html easyengine/nginx-proxy:v" . EE_VERSION;


			if ( EE::docker()::boot_container( $proxy_type, $ee_proxy_command ) ) {
				EE::success( "$proxy_type container is up." );
			} else {
				EE::error( "There was some error in starting $proxy_type container. Please check logs." );
			}
		}
	}
}

/**
 * Creates site root directory if does not exist.
 * Throws error if it does exist.
 *
 * @param string $site_root Root directory of the site.
 * @param string $site_name Name of the site.
 */
function create_site_root( $site_root, $site_name ) {
	$fs = new Filesystem();
	if ( $fs->exists( $site_root ) ) {
		EE::error( "Webroot directory for site $site_name already exists." );
	}

	$whoami            = EE::launch( 'whoami', false, true );
	$terminal_username = rtrim( $whoami->stdout );

	$fs->mkdir( $site_root );
	$fs->chown( $site_root, $terminal_username );
}

/**
 * Function to setup site network.
 *
 * @param string $site_name Name of the site.
 *
 * @throws \Exception when network start fails.
 */
function setup_site_network( $site_name ) {
	$proxy_type = EE_PROXY_TYPE;
	if ( EE::docker()::create_network( $site_name ) ) {
		EE::success( 'Network started.' );
	} else {
		throw new \Exception( 'There was some error in starting the network.' );
	}

	EE::docker()::connect_site_network_to( $site_name, $proxy_type );

}

/**
 * Adds www to non-www redirection to site
 *
 * @param string $site_name Name of the site.
 * @param bool   $le        Specifying if letsencrypt is enabled or not.
 */
function add_site_redirects( $site_name, $le ) {
	$fs               = new Filesystem();
	$confd_path       = EE_CONF_ROOT . '/nginx/conf.d/';
	$config_file_path = $confd_path . $site_name . '-redirect.conf';
	$has_www          = strpos( $site_name, 'www.' ) === 0;

	if ( $has_www ) {
		$site_name_without_www = ltrim( $site_name, '.www' );
		// ee site create www.example.com --le
		if ( $le ) {
			$content = "
server {
	listen  80;
	listen  443;
	server_name  $site_name_without_www;
	return  301 https://$site_name\$request_uri;
}";
		} // ee site create www.example.com
		else {
			$content = "
server {
	listen  80;
	server_name  $site_name_without_www;
	return  301 http://$site_name\$request_uri;
}";
		}
	} else {
		$site_name_with_www = 'www.' . $site_name;
		// ee site create example.com --le
		if ( $le ) {

			$content = "
server {
	listen  80;
	listen  443;
	server_name  $site_name_with_www;
	return  301 https://$site_name\$request_uri;
}";
		} // ee site create example.com
		else {
			$content = "
server {
	listen  80;
	server_name  $site_name_with_www;
	return  301 http://$site_name\$request_uri;
}";
		}
	}
	$fs->dumpFile( $config_file_path, ltrim( $content, PHP_EOL ) );
}

/**
 * Function to create entry in /etc/hosts.
 *
 * @param string $site_name Name of the site.
 */
function create_etc_hosts_entry( $site_name ) {

	$host_line = LOCALHOST_IP . "\t$site_name";
	$etc_hosts = file_get_contents( '/etc/hosts' );
	if ( ! preg_match( "/\s+$site_name\$/m", $etc_hosts ) ) {
		if ( EE\Utils\default_launch( "/bin/bash -c 'echo \"$host_line\" >> /etc/hosts'" ) ) {
			EE::success( 'Host entry successfully added.' );
		} else {
			EE::warning( "Failed to add $site_name in host entry, Please do it manually!" );
		}
	} else {
		EE::log( 'Host entry already exists.' );
	}
}


/**
 * Checking site is running or not.
 *
 * @param string $site_name Name of the site.
 *
 * @throws \Exception when fails to connect to site.
 */
function site_status_check( $site_name ) {
	EE::log( 'Checking and verifying site-up status. This may take some time.' );
	$httpcode = get_curl_info( $site_name );
	$i        = 0;
	while ( 200 !== $httpcode && 302 !== $httpcode ) {
		EE::debug( "$site_name status httpcode: $httpcode" );
		$httpcode = get_curl_info( $site_name );
		echo '.';
		sleep( 2 );
		if ( $i ++ > 60 ) {
			break;
		}
	}
	if ( 200 !== $httpcode && 302 !== $httpcode ) {
		throw new \Exception( 'Problem connecting to site!' );
	}

}

/**
 * Function to get httpcode or port occupancy info.
 *
 * @param string $url     url to get info about.
 * @param int $port       The port to check.
 * @param bool $port_info Return port info or httpcode.
 *
 * @return bool|int port occupied or httpcode.
 */
function get_curl_info( $url, $port = 80, $port_info = false ) {

	$ch = curl_init( $url );
	curl_setopt( $ch, CURLOPT_HEADER, true );
	curl_setopt( $ch, CURLOPT_NOBODY, true );
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
	curl_setopt( $ch, CURLOPT_TIMEOUT, 10 );
	curl_setopt( $ch, CURLOPT_PORT, $port );
	curl_exec( $ch );
	if ( $port_info ) {
		return empty( curl_getinfo( $ch, CURLINFO_PRIMARY_IP ) );
	}

	return curl_getinfo( $ch, CURLINFO_HTTP_CODE );
}

/**
 * Function to pull the latest images and bring up the site containers.
 *
 * @param string $site_root Root directory of the site.
 *
 * @throws \Exception when docker-compose up fails.
 */
function start_site_containers( $site_root ) {
	EE::log( 'Pulling latest images. This may take some time.' );
	chdir( $site_root );
	\EE\Utils\default_launch( 'docker-compose pull' );
	EE::log( 'Starting site\'s services.' );
	if ( ! EE::docker()::docker_compose_up( $site_root ) ) {
		throw new \Exception( 'There was some error in docker-compose up.' );
	}
}


/**
 * Generic function to run a docker compose command. Must be ran inside correct directory.
 */
function run_compose_command( $action, $container, $action_to_display = null, $service_to_display = null ) {
	$display_action  = $action_to_display ? $action_to_display : $action;
	$display_service = $service_to_display ? $service_to_display : $container;

	\EE::log( ucfirst( $display_action ) . 'ing ' . $display_service );
	\EE\Utils\default_launch( "docker-compose $action $container", true, true );
}
