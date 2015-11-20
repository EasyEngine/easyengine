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
	 * [<packages>]
	 * : install single packages
	 *[--categories=<categories>]
	 * : Install packages
	 *
	 */
	public function install( $args, $assoc_args ) {

		if ( isset( $assoc_args['categories']) ) {
			$install = $this->install_categories($assoc_args);
			EE_CLI::success('Package succesfully installed : ' . $assoc_args['categories']);

		}
		elseif (isset($args[0])) {
			$install = $this->install_package($args);
			EE_CLI::success('Package succesfully installed : ' . $args[0]);

		}
		//Read stack name to be installed from optional arguments
		//Check if stack configuration for provided arguments list exists.
		//If stack exists parse configuration for stack.
		/** Check if stack configuration and system matches. for example.
			if stack_type is apt and system supports yum then error must be thrown.
		*/
		// else If configuration matches the system then Installation process should be
		// carried out accordingly.


	}

	 private function install_categories( $assoc_args )
	 {

		 //check
		 $Data = Spyc::YAMLLoad('/home/rtcamp/Desktop/developments/ee-cli.yml');
		 //print_r($Data);

		 foreach ($Data as $key => $value) {

			 foreach ($value as $key_inner => $values_inner) {
				 if ($key_inner == 'category' and $values_inner = $assoc_args['categories']) {
					 $arg[0]= $key;
					 $install = $this->install_package( $arg );

				 }
			 }
		 }
	 }

	private function install_package( $args ){
		print_r($args[0]);

		$Data = Spyc::YAMLLoad('/home/prabuddha/Desktop/ee4.4.0.0/ee-cli.yml');
		//print_r($Data);
		print_r($Data[$args[0]]);

		if (isset($Data[$args[0]])) {
			EE_CLI::success('installing packages from ee-config.cli: ' . $args[0]);
			EE_CLI::success('installing package_name: ' . $Data[$args[0]]['package_name']);
			EE_CLI::success('Adding repository: ' . $Data[$args[0]]['apt_repository']);

		}
		include EE_CLI_ROOT . '/php/Stack/apt.php';

		$apt = new APT($Data[$args[0]]);


		if($apt->validate_stack_type($Data[$args[0]]['stack_type'])){
			$apt->install();
		}
		else {
			echo "please check config";
		}



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
