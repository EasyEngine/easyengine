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
 * @param array  $args     The passed arguments.
 * @param String $command  The command passing the arguments to auto-detect site-name.
 * @param String $function The function passing the arguments to auto-detect site-name.
 * @param bool   $arg_zero Site-name will be present in the first argument. Default true.
 *
 * @return array Arguments with site-name set.
 */
function auto_site_name( $args, $command, $function, $arg_zero = true ) {
	if ( isset( $args[0] ) ) {
		if ( EE::db()::site_in_db( $args[0] ) ) {
			return $args;
		}
	}
	$site_name = get_site_name();
	if ( $site_name ) {
		if ( isset( $args[0] ) && $arg_zero ) {
			EE::error( $args[0] . " is not a valid site-name. Did you mean `ee $command $function $site_name`?" );
		}
		array_unshift( $args, $site_name );
	} else {
		EE::error( "Could not find the site you wish to run $command $function command on.\nEither pass it as an argument: `ee $command $function <site-name>` \nor run `ee $command $function` from inside the site folder." );
	}

	return $args;
}
