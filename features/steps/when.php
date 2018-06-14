<?php
// echo ;
include_once( __DIR__ . '/../../php/class-ee.php' );
include_once( __DIR__ . '/../../php/utils.php' );

$steps->When(
	"/^I run \'([^\']*)\'$/",
	function ( $world, $command ) {
		$launch = EE::launch($command);
		$world->output = trim( implode( "\n", $launch->output ) );
	}
);
