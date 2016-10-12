<?php

// Can be used by plugins to check if ee-cli is running or not
define( 'EE_CLI', true );
define( 'EE_CLI_VERSION', '4.0.0' );
define( 'EE_CLI_START_MICROTIME', microtime( true ) );

include EE_CLI_ROOT . '/app/utils.php';
include EE_CLI_ROOT . '/app/dispatcher.php';
include EE_CLI_ROOT . '/app/class-ee-cli.php';
include EE_CLI_ROOT . '/app/class-ee-cli-command.php';
include EE_CLI_ROOT . '/app/core/ee-variables.php';
include EE_CLI_ROOT . '/app/core/ee-os.php';
include EE_CLI_ROOT . '/app/core/ee-apt-get.php';
include EE_CLI_ROOT . '/app/core/ee-repo.php';

\EE_CLI\Utils\load_dependencies();

EE_CLI::get_runner()->start();
