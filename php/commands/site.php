<?php

/**
 * Manage EasyEngine sites.
 *
 * ## EXAMPLES
 *
 *     ee site create my_domain
 *
 *     ee site delete my_domain
 */
class Site_Command extends EE_CLI_Command {

	/**
	 * Remove a value from the object cache.
	 *
	 * <sitename>
	 * : Cache key.
	 *[--cache=<cache>]
	 * : Webroot
	 * [--user]
	 * [--pass]
	 * [--email]
	 * : Method for grouping data within the cache which allows the same key to be used across groups.
	 */

	public function create( $args, $assoc_args ) {

		if (isset($assoc_args['cache'])){

			EE_CLI::success( 'Site Successfully created with ' . $assoc_args['cache'] . ' cache.' );
		}


	}


	/**
	 * Remove a value from the object cache.
	 *
	 * <sitename>
	 * : Cache key.
	 *[--files]
	 * : Webroot
	 * [--db=<some_value>]
	 * : Method for grouping data within the cache which allows the same key to be used across groups.
	 */
	public function delete( $args, $assoc_args ) {
		EE_CLI::success( 'Object deleted.' );
	}

}

EE_CLI::add_command( 'site', 'Site_Command' );
