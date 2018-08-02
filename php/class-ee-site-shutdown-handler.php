<?php

/**
 * Class Shutdown_Handler
 */
class Shutdown_Handler {

	/**
	 * Handle fatal errors. This function was created as the register_shutdown_function requires the callable function to be public and any public function inside site-command would be callable directly through command-line.
	 *
	 * @param array $site_command having Site_Command object.
	 */
	public function cleanup( $site_command ) {
		$reflector = new ReflectionObject( $site_command[0] );
		$method    = $reflector->getMethod( 'shutDownFunction' );
		$method->setAccessible( true );
		$method->invoke( $site_command[0] );
	}
}
