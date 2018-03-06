<?php

// Store the path to the Phar early on for `Utils\phar-safe-path()` function.
define( 'EE_PHAR_PATH', getcwd() );

if ( file_exists( 'phar://ee.phar/php/ee.php' ) ) {
	define( 'EE_ROOT', 'phar://ee.phar' );
	include EE_ROOT . '/php/ee.php';
} elseif ( file_exists( 'phar://ee.phar/vendor/ee/ee/php/ee.php' ) ) {
	define( 'EE_ROOT', 'phar://ee.phar/vendor/ee/ee' );
	include EE_ROOT . '/php/ee.php';
} else {
	echo "Couldn't find 'php/ee.php'. Was this Phar built correctly?";
	exit( 1 );
}
