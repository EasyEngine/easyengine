<?php

# Global configuration file for EE.

return array(

	'disabled_commands' => array(
		'file' => '<list>',
		'default' => array(),
		'desc' => '(Sub)commands to disable.',
	),

	'sites_path' => array(
		'runtime' => '=<path>',
		'file' => '<path>',
		'default' => null,
		'desc' => 'Absolute path to where all sites will be stored.',
	),

	'locale' => array(
		'runtime' => '=<locale>',
		'file'    => '<locale>',
		'default' => null,
		'desc'    => 'Locale for WordPress.',
	),

	'ee_installer_version' => array(
		'file' => '<path>',
		'default' => null,
		'desc' => 'EE version to run.',
	),

	'color' => array(
		'runtime' => true,
		'file' => '<bool>',
		'default' => 'auto',
		'desc' => 'Whether to colorize the output.',
	),

	'debug' => array(
		'runtime' => '[=<group>]',
		'file' => '<group>',
		'default' => false,
		'desc' => 'Show all PHP errors; add verbosity to EE bootstrap.',
	),

	'prompt' => array(
		'runtime' => '[=<assoc>]',
		'file' => false,
		'default' => false,
		'desc' => 'Prompt the user to enter values for all command arguments, or a subset specified as comma-separated values.',
	),

	'quiet' => array(
		'runtime' => '',
		'file' => '<bool>',
		'default' => false,
		'desc' => 'Suppress informational messages.',
	),

);
