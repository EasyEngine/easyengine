<?php


$steps->Given(
	"/^\'([^\']*)\' is installed$/",
	function ( $world, $package ) {
		exec( "type " . $package, $output, $return_status );
		if ( 0 !== $return_status ) {
			throw new Exception( $package . " is not installed! Code:" . $return_status );
		}
	}
);

$steps->Given(
	"/^running container \'([^\']*)\'$/",
	function ( $world, $container ) {
		exec( "docker inspect -f '{{.State.Running}}' " . $container, $output, $code );
		//		$output = trim(implode("\n", $output));
		if ( 0 !== $code ) {
			throw new Exception( $container . " is not available!" );
		}
	}
);
/*
$steps->Given('/^Webroot path in config$/', function($world) {
	//throw new \Behat\Behat\Exception\PendingException();
	$world->webroot_path = rtrim( \EE::get_runner()->config['sites_path'], '/\\' ) . '/';
});
*/
