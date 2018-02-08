<?php

# Global configuration file for EE.

return array(

	'ssh' => array(
		'runtime' => '=[<scheme>:][<user>@]<host|container>[:<port>][<path>]',
		'file' => '[<scheme>:][<user>@]<host|container>[:<port>][<path>]',
		'desc' => 'Perform operation against a remote server over SSH (or a container using scheme of "docker", "docker-compose", "vagrant").',
	),

	# Used by wp-cli to control wp site remotely.
	# Might be useful in future for controlling ee remotely.
	// 'http' => array(
	// 	'runtime' => '=<http>',
	// 	'file' => '<http>',
	// 	'desc' => 'Reserved for future.',
	// ),

	'skip-packages' => array(
		'runtime'   => '',
		'file'      => '<bool>',
		'desc'      => 'Skip loading all installed packages.',
		'default'   => false,
	),

	'require' => array(
		'runtime' => '=<path>',
		'file' => '<path>',
		'desc' => 'Load PHP file before running the command (may be used more than once).',
		'multiple' => true,
		'default' => array(),
	),

	'disabled_commands' => array(
		'file' => '<list>',
		'default' => array(),
		'desc' => '(Sub)commands to disable.',
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

	# --allow-root => (NOT RECOMMENDED) Allow ee to run as root. This poses
	# a security risk, so you probably do not want to do this.
	'allow-root' => array(
		'file' => false, # Explicit. Just in case the default changes.
		'runtime' => '',
		'hidden'  => true,
	),

);
