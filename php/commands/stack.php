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
class Stack_Command extends EE_CLI_Command {

	/**
	 * Remove a value from the object cache.
	 *
	 *
	 *
	 *[--package=<package>]
	 *
	 *
	 * : Method for grouping data within the cache which allows the same key to be used across groups.
	 */

	public function install( $args, $assoc_args ) {

		if (isset($assoc_args['package'])){

			EE_CLI::success( 'Site Successfully created with ' . $assoc_args['package'] . ' cache.' );
		}


	}



	public function remove( $args, $assoc_args ) {

		//removing packages


	}

	public function purge( $args, $assoc_args ) {


	}

	public function start( $args, $assoc_args ) {

		//removing packages


	}


	public function stop( $args, $assoc_args ) {

		//removing packages


	}




	public function upgrade( $args, $assoc_args ) {

		//removing packages


	}





}

EE_CLI::add_command( 'stack', 'Stack_Command' );
