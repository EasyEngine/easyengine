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
		//Read stack name to be installed from optional arguments
		//Check if stack configuration for provided arguments list exists.
		//If stack exists parse configuration for stack.
		/** Check if stack configuration and system matches. for example.
			if stack_type is apt and system supports yum then error must be thrown.
		*/
		// else If configuration matches the system then Installation process should be
		// carried out accordingly.
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
		//Read stack name to be removed from optional arguments
		//Check if stack configuration for provided arguments list exists.
		//If stack exists parse configuration for stack.
		/** Check if stack configuration and system matches. for example.
			if stack_type is apt and system supports yum then error must be thrown.
		*/
		// else If configuration matches the system then Removal process should be
		// carried out accordingly.
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
		//Purge command is alias for the remove command.
		// so we must add alias of this command for remove.

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
		// Read service name from command arguments.
		//Check if service exists in that stack config.
		//start service
	}

	/**
	 * Install package into system.
	 *[--package=<package>]
	 * : Install packages
	 *
	 */
	public function stop( $args, $assoc_args ) {

		EE_CLI::success( 'Service succesfully stopped : ' . $assoc_args['package']  );
		// Read service name from command arguments.
		//Check if service exists in that stack config.
		//start service

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
		//Read stack name to be removed from optional arguments
		//Check if stack configuration for provided arguments list exists.
		//If stack exists parse configuration for stack.
		/** Check if stack configuration and system matches. for example.
			if stack_type is apt and system supports yum then error must be thrown.
		*/
		// else If configuration matches the system then upgrade process should be
		// carried out.
			// upgrade process will need to be decided on stack_type.
			//If stack_type is apt then it can be done via `apt-get upgrade`
			//If stack_type is composer then it should be done via composer update.
			//Like wise for other stack types.
	}

}

EE_CLI::add_command( 'stack', 'Stack_Command' );
