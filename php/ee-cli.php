<?php

// Can be used by plugins/themes to check if WP-CLI is running or not
define( 'EE_CLI', true );
define( 'EE_CLI_VERSION', trim( file_get_contents( EE_CLI_ROOT . '/VERSION' ) ) );
define( 'EE_CLI_START_MICROTIME', microtime( true ) );

// Set common headers, to prevent warnings from plugins
$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.0';
$_SERVER['HTTP_USER_AGENT'] = '';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

include EE_CLI_ROOT . '/php/utils.php';
include EE_CLI_ROOT . '/php/dispatcher.php';
include EE_CLI_ROOT . '/php/class-ee-cli.php';
include EE_CLI_ROOT . '/php/class-ee-cli-command.php';

\EE_CLI\Utils\load_dependencies();

EE_CLI::get_runner()->start();
