<?php

if ( ! class_exists( 'CLI_Command' ) ) {
	require_once __DIR__ . '/src/CLI_Command.php';
}

EE::add_command( 'cli', 'CLI_Command' );
