<?php

/**
 * Manage EasyEngine stack.
 *
 * ## EXAMPLES
 *
 *     ee stack install
 *
 *     ee stack install --package
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
	 *
	 */

	public function install( $args, $assoc_args ) {

		if (isset($assoc_args['package'])){

			EE_CLI::success( 'Package succesfully installed : ' . $assoc_args['package']  );
		}


	}



	public function remove( $args, $assoc_args ) {

		//removing packages


	}

	public function purge( $args, $assoc_args ) {
		//purging packages

	}

	public function start( $args, $assoc_args ) {

		//start service


	}


	public function stop( $args, $assoc_args ) {

		//stop service


	}




	public function upgrade( $args, $assoc_args ) {

		//removing packages


	}





}

EE_CLI::add_command( 'stack', 'Stack_Command' );
