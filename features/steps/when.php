<?php
// echo ;
include_once( __DIR__ . '/../../php/class-ee.php' );
include_once( __DIR__ . '/../../php/utils.php' );

$steps->When(
	"/^I run \'([^\']*)\'$/",
	function ( $world, $command ) {
		$world->command = EE::launch($command, false, true);
//		$world->output = trim( implode( "\n", $launch->stdout) );
		$a=1;
	}
);
