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
	 * Install package into system.
	 *[--package=<package>]
	 * : Install packages
	 *
	 */
	public function install( $args, $assoc_args ) {
		EE_CLI::success( 'Package succesfully installed : ' . $assoc_args['package']  );
	}

	/**
	 * Install package into system.
	 *[--package=<package>]
	 * : Install packages
	 *
	 */
	public function remove( $args, $assoc_args ) {

		//removing packages
		EE_CLI::success( 'Package succesfully removed : ' . $assoc_args['package']  );

	}

	/**
	 * Install package into system.
	 *[--package=<package>]
	 * : Install packages
	 *
	 */
	public function purge( $args, $assoc_args ) {
		//purging packages
		EE_CLI::success( 'Package succesfully purged : ' . $assoc_args['package']  );

	}

	/**
	 * Install package into system.
	 *[--package=<package>]
	 * : Install packages
	 *
	 */
	public function start( $args, $assoc_args ) {

		//start service
		EE_CLI::success( 'Service succesfully started : ' . $assoc_args['package']  );
	}

	/**
	 * Install package into system.
	 *[--package=<package>]
	 * : Install packages
	 *
	 */
	public function stop( $args, $assoc_args ) {

		//stop service
		EE_CLI::success( 'Service succesfully stopped : ' . $assoc_args['package']  );
	}

	/**
	 * Install package into system.
	 *[--package=<package>]
	 * : Install packages
	 *
	 */
	public function upgrade( $args, $assoc_args ) {

		//upgraded packages
		EE_CLI::success( 'Package succesfully upgraded : ' . $assoc_args['package']  );
	}

}

EE_CLI::add_command( 'stack', 'Stack_Command' );
