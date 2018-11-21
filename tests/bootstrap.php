<?php

define( 'EE_ROOT', dirname( __DIR__ ) );

/**
 * Compatibility with PHPUnit 6+
 */
if ( class_exists( 'PHPUnit\Runner\Version' ) ) {
	require_once __DIR__ . '/phpunit6-compat.php';
}

if ( file_exists( EE_ROOT . '/vendor/autoload.php' ) ) {
	define( 'EE_VENDOR_DIR' , EE_ROOT . '/vendor' );
} elseif ( file_exists( dirname( dirname( EE_ROOT ) ) . '/autoload.php' ) ) {
	define( 'EE_VENDOR_DIR' , dirname( dirname( EE_ROOT ) ) );
}

require_once EE_VENDOR_DIR . '/autoload.php';
require_once EE_ROOT . '/php/utils.php';

