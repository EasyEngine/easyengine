<?php

// Can be used by plugins/themes to check if EE is running or not
define( 'EE', true );
define( 'EE_VERSION', trim( file_get_contents( EE_ROOT . '/VERSION' ) ) );
define( 'EE_START_MICROTIME', microtime( true ) );
// PHP_OS, not php_uname(): that can be disabled, and this runs before the requirements check.
define( 'IS_DARWIN', 'Darwin' === PHP_OS );

if ( IS_DARWIN ) {
	define( 'EE_ROOT_DIR', rtrim( getenv( 'HOME' ), '/\\' ) . '/easyengine' );
} else {
	define( 'EE_ROOT_DIR', '/opt/easyengine' );
}

define( 'EE_BACKUP_DIR', EE_ROOT_DIR . '/.backup' );
define( 'EE_SERVICE_DIR', EE_ROOT_DIR . '/services' );

if ( file_exists( EE_ROOT . '/vendor/autoload.php' ) ) {
	define( 'EE_VENDOR_DIR', EE_ROOT . '/vendor' );
} elseif ( file_exists( dirname( dirname( EE_ROOT ) ) . '/autoload.php' ) ) {
	define( 'EE_VENDOR_DIR', dirname( dirname( EE_ROOT ) ) );
} else {
	define( 'EE_VENDOR_DIR', EE_ROOT . '/vendor' );
}

require_once EE_ROOT . '/php/bootstrap.php';
\EE\bootstrap();
