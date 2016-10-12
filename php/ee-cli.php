<?php

// Can be used by plugins to check if ee-cli is running or not
define( 'EE_CLI', true );
define( 'EE_CLI_VERSION', '4.0.0' );
define( 'EE_CLI_START_MICROTIME', microtime( true ) );

include EE_ROOT . '/php/utils.php';
include EE_ROOT . '/php/dispatcher.php';
include EE_ROOT . '/php/class-ee-cli.php';
include EE_ROOT . '/php/class-ee-cli-command.php';
include EE_ROOT . '/php/core/ee-variables.php';
include EE_ROOT . '/php/core/ee-os.php';
include EE_ROOT . '/php/core/ee-apt-get.php';
include EE_ROOT . '/php/core/ee-repo.php';

\EE_CLI\Utils\load_dependencies();

EE::get_runner()->start();
