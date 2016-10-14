<?php

// Can be used by plugins to check if ee-cli is running or not
define( 'EE', true );
define( 'EE_VERSION', '4.0.0' );
define( 'EE_START_MICROTIME', microtime( true ) );

include EE_ROOT . '/php/utils.php';
include EE_ROOT . '/php/dispatcher.php';
include EE_ROOT . '/php/class-ee.php';
include EE_ROOT . '/php/class-ee-command.php';
include EE_ROOT . '/php/utils/class-ee-variables.php';
include EE_ROOT . '/php/utils/class-ee-os.php';
include EE_ROOT . '/php/utils/class-ee-apt-get.php';
include EE_ROOT . '/php/utils/class-ee-repo.php';
include EE_ROOT . '/php/utils/class-ee-git.php';
include EE_ROOT . '/php/utils/class-ee-mysql.php';

\EE\Utils\load_dependencies();

EE::get_runner()->start();
