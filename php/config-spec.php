<?php

return array(
	'config' => array(
		'deprecated' => 'Use the EE_CLI_CONFIG_PATH environment variable instead.',
		'runtime' => '=<path>',
	),

	'color' => array(
		'runtime' => true,
		'file' => '<bool>',
		'default' => 'auto',
		'desc' => 'Whether to colorize the output',
	),

	'debug' => array(
		'runtime' => '',
		'file' => '<bool>',
		'default' => false,
		'desc' => 'Show all PHP errors; add verbosity to WP-CLI bootstrap',
	),

	'prompt' => array(
		'runtime' => '',
		'file' => false,
		'default' => false,
		'desc' => 'Prompt the user to enter values for all command arguments',
	),

	'quiet' => array(
		'runtime' => '',
		'file' => '<bool>',
		'default' => false,
		'desc' => 'Suppress informational messages',
	),
);
