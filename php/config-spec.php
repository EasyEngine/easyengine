<?php

return array(
	'color' => array(
		'runtime' => true,
		'file' => '<bool>',
		'default' => 'auto',
		'desc' => 'Whether to colorize the output.',
	),

	'debug' => array(
		'runtime' => '',
		'file' => '<bool>',
		'default' => false,
		'desc' => 'Show all PHP errors; add verbosity to ee-cli bootstrap.',
	),

	'prompt' => array(
		'runtime' => '[=<assoc>]',
		'file' => false,
		'default' => false,
		'desc' => 'Prompt the user to enter values for all command arguments, or a subset specified as comma-separated values.',
	),

);
