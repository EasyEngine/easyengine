<?php

namespace EE\SiteUtils;

use \EE;
use EE\Model\Site;
use \Symfony\Component\Filesystem\Filesystem;

/**
 * Get the site-name from the path from where ee is running if it is a valid site path.
 *
 * @throws \Exception
 *
 * @return bool|String Name of the site or false in failure.
 */
function get_site_name() {
	$sites = Site::all( [ 'site_url' ] );

	if ( ! empty( $sites ) ) {
		$cwd = getcwd();
		$name_in_path = explode( '/', $cwd );

		$site_name = array_intersect( array_column( $sites, 'site_url' ), $name_in_path );

		if ( 1 === count( $site_name ) ) {
			$name = reset( $site_name );
			$path = Site::find( $name );
			if ( $path ) {
				$site_path = $path->site_fs_path;
				if ( substr( $cwd, 0, strlen( $site_path ) ) === $site_path ) {
					return $name;
				}
			}
		}
	}

	return false;
}

/**
 * Function to set the site-name in the args when ee is running in a site folder and the site-name has not been passed
 * in the args. If the site-name could not be found it will throw an error.
 *
 * @param array   $args     The passed arguments.
 * @param string  $command  The command passing the arguments to auto-detect site-name.
 * @param string  $function The function passing the arguments to auto-detect site-name.
 * @param integer $arg_pos  Argument position where Site-name will be present.
 *
 * @throws EE\ExitException
 *
 * @return array Arguments with site-name set.
 */
function auto_site_name( array $args, string $command, string $function, int $arg_pos = 0 ) : array {

	if ( isset( $args[ $arg_pos ] ) ) {
		if ( Site::find( $args[ $arg_pos ] ) ) {
			return $args;
		}
	}
	$site_name = get_site_name();
	if ( $site_name ) {
		if ( isset( $args[ $arg_pos ] ) ) {
			EE::error( $args[ $arg_pos ] . " is not a valid site-name. Did you mean `ee $command $function $site_name`?" );
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

			$fs = new Filesystem();

			if ( ! $fs->exists( EE_CONF_ROOT . '/docker-compose.yml' ) ) {
				generate_global_docker_compose_yml( $fs );
			}

			$EE_CONF_ROOT = EE_CONF_ROOT;
			if ( ! EE::docker()::docker_network_exists( 'ee-global-network' ) ) {
				if ( ! EE::docker()::create_network( 'ee-global-network' ) ) {
					EE::error( 'Unable to create network ee-global-network' );
				}
			}
			if ( EE::docker()::docker_compose_up( EE_CONF_ROOT, [ 'nginx-proxy' ] ) ) {
				$fs->dumpFile( "$EE_CONF_ROOT/nginx/conf.d/custom.conf", file_get_contents( EE_ROOT . '/templates/custom.conf.mustache' ) );
				EE::success( "$proxy_type container is up." );
			} else {
				EE::error( "There was some error in starting $proxy_type container. Please check logs." );
			}
		}
	}
}

/**
 * Generates global docker-compose.yml at EE_CONF_ROOT
 *
 * @param Filesystem $fs Filesystem object to write file
 */
function generate_global_docker_compose_yml( Filesystem $fs ) {
	$img_versions = EE\Utils\get_image_versions();

	$data     = [
		'services' => [
			'name'           => 'nginx-proxy',
			'container_name' => 'ee-nginx-proxy',
			'image'          => 'easyengine/nginx-proxy:' . $img_versions['easyengine/nginx-proxy'],
			'restart'        => 'always',
			'ports'          => [
				'80:80',
				'443:443',
			],
			'environment'    => [
				'LOCAL_USER_ID=' . posix_geteuid(),
				'LOCAL_GROUP_ID=' . posix_getegid(),
			],
			'volumes'        => [
				EE_CONF_ROOT . '/nginx/certs:/etc/nginx/certs',
				EE_CONF_ROOT . '/nginx/dhparam:/etc/nginx/dhparam',
				EE_CONF_ROOT . '/nginx/conf.d:/etc/nginx/conf.d',
				EE_CONF_ROOT . '/nginx/htpasswd:/etc/nginx/htpasswd',
				EE_CONF_ROOT . '/nginx/vhost.d:/etc/nginx/vhost.d',
				'/usr/share/nginx/html',
				'/var/run/docker.sock:/tmp/docker.sock:ro',
			],
			'networks'       => [
				'global-network',
			],
		],
	];

	$contents = EE\Utils\mustache_render( EE_ROOT . '/templates/global_docker_compose.yml.mustache', $data );
	$fs->dumpFile( EE_CONF_ROOT . '/docker-compose.yml', $contents );
}

/**
 * Creates site root directory if does not exist.
 * Throws error if it does exist.
 *
 * @param string $site_root Root directory of the site.
 * @param string $site_name Name of the site.
 *
 * @throws EE\ExitException
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
 * Reloads configuration of ee-nginx-proxy container
 *
 * @return bool
 */
function reload_proxy_configuration() {
	return EE::exec( 'docker exec ee-nginx-proxy sh -c "/app/docker-entrypoint.sh /usr/local/bin/docker-gen /app/nginx.tmpl /etc/nginx/conf.d/default.conf; /usr/sbin/nginx -s reload"' );
}

/**
 * Adds www to non-www redirection to site
 *
 * @param string $site_name name of the site.
 * @param bool   $ssl       enable ssl or not.
 * @param bool   $inherit   inherit cert or not.
 */
function add_site_redirects( string $site_name, bool $ssl, bool $inherit ) {

	$fs               = new Filesystem();
	$confd_path       = EE_CONF_ROOT . '/nginx/conf.d/';
	$config_file_path = $confd_path . $site_name . '-redirect.conf';
	$has_www          = strpos( $site_name, 'www.' ) === 0;
	$cert_site_name   = $site_name;

	if ( $inherit ) {
		$cert_site_name = implode( '.', array_slice( explode( '.', $site_name ), 1 ) );
	}

	if ( $has_www ) {
		$server_name = ltrim( $site_name, '.www' );
	} else {
		$server_name = 'www.' . $site_name;
	}

	$conf_data = [
		'site_name'      => $site_name,
		'cert_site_name' => $cert_site_name,
		'server_name'    => $server_name,
		'ssl'            => $ssl,
	];

	$content = EE\Utils\mustache_render( EE_ROOT . '/templates/redirect.conf.mustache', $conf_data );
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
		if ( EE::exec( "/bin/bash -c 'echo \"$host_line\" >> /etc/hosts'" ) ) {
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
	while ( 200 !== $httpcode && 302 !== $httpcode && 301 !== $httpcode ) {
		EE::debug( "$site_name status httpcode: $httpcode" );
		$httpcode = get_curl_info( $site_name );
		echo '.';
		sleep( 2 );
		if ( $i ++ > 60 ) {
			break;
		}
	}
	if ( 200 !== $httpcode && 302 !== $httpcode && 301 !== $httpcode ) {
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
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
	curl_setopt( $ch, CURLOPT_NOBODY, true );
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
 * @param string $site_root  Root directory of the site.
 * @param array  $containers The minimum required conatainers to start the site. Default null, leads to starting of all containers.
 *
 * @throws \Exception when docker-compose up fails.
 */
function start_site_containers( $site_root, $containers = [] ) {

	EE::log( 'Pulling latest images. This may take some time.' );
	chdir( $site_root );
	EE::exec( 'docker-compose pull' );
	EE::log( 'Starting site\'s services.' );
	if ( ! EE::docker()::docker_compose_up( $site_root, $containers ) ) {
		throw new \Exception( 'There was some error in docker-compose up.' );
	}
}


/**
 * Generic function to run a docker compose command. Must be ran inside correct directory.
 *
 * @param string $action             docker-compose action to run.
 * @param string $container          The container on which action has to be run.
 * @param string $action_to_display  The action message to be displayed.
 * @param string $service_to_display The service message to be displayed.
 */
function run_compose_command( $action, $container, $action_to_display = null, $service_to_display = null ) {

	$display_action  = $action_to_display ? $action_to_display : $action;
	$display_service = $service_to_display ? $service_to_display : $container;

	EE::log( ucfirst( $display_action ) . 'ing ' . $display_service );
	EE::exec( "docker-compose $action $container", true, true );
}

/**
 * Function to copy and configure files needed for postfix.
 *
 * @param string $site_name     Name of the site to configure postfix files for.
 * @param string $site_conf_dir Configuration directory of the site `site_root/config`.
 *
 * @throws \Exception
 */
function set_postfix_files( $site_name, $site_conf_dir ) {

	$fs = new Filesystem();
	$fs->mkdir( $site_conf_dir . '/postfix' );
	$fs->mkdir( $site_conf_dir . '/postfix/ssl' );
	$ssl_dir = $site_conf_dir . '/postfix/ssl';

	if ( ! EE::exec( sprintf( "openssl req -new -x509 -nodes -days 365 -subj \"/CN=smtp.%s\" -out $ssl_dir/server.crt -keyout $ssl_dir/server.key", $site_name ) )
		&& EE::exec( "chmod 0600 $ssl_dir/server.key" ) ) {
		throw new \Exception( 'Unable to generate ssl key for postfix' );
	}
}

/**
 * Function to execute docker-compose exec calls to postfix to get it configured and running for the site.
 *
 * @param string $site_name Name of the for which postfix has to be configured.
 * @param string $site_root  Site root.
 */
function configure_postfix( $site_name, $site_root ) {

	chdir( $site_root );
	EE::exec( 'docker-compose exec postfix postconf -e \'relayhost =\'' );
	EE::exec( 'docker-compose exec postfix postconf -e \'smtpd_recipient_restrictions = permit_mynetworks\'' );
	$launch      = EE::launch( sprintf( 'docker inspect -f \'{{ with (index .IPAM.Config 0) }}{{ .Subnet }}{{ end }}\' %s', $site_name ) );
	$subnet_cidr = trim( $launch->stdout );
	EE::exec( sprintf( 'docker-compose exec postfix postconf -e \'mynetworks = %s 127.0.0.0/8\'', $subnet_cidr ) );
	EE::exec( sprintf( 'docker-compose exec postfix postconf -e \'myhostname = %s\'', $site_name ) );
	EE::exec( 'docker-compose exec postfix postconf -e \'syslog_name = $myhostname\'' );
	EE::exec( 'docker-compose restart postfix' );
}
