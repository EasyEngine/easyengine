<?php

if ( ! class_exists( 'LOG_Command' ) ) {
	require_once __DIR__ . '/src/LOG_Command.php';
}

EE::add_command( 'log', 'LOG_Command' );