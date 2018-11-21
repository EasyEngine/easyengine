<?php

// This file needs to parse without error in PHP < 7.0

if ( 'cli' !== PHP_SAPI ) {
	echo "Only CLI access.\n";
	die( -1 );
}

if ( version_compare( PHP_VERSION, '7.0', '<' ) ) {
	printf( "Error: EE requires PHP %s or newer. You are running version %s.\n", '7.0', PHP_VERSION );
	die( -1 );
}

define( 'EE_ROOT', dirname( __DIR__ ) );

include_once EE_ROOT . '/php/init-ee.php';

