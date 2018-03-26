<?php

$steps->When(
	"/^I run \'([^\']*)\'$/",
	function ( $world, $command ) {
		exec( $command, $output );
		$world->output = trim( implode( "\n", $output ) );
	}
);
