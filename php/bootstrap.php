<?php

namespace EE;

use EE\Bootstrap\BootstrapState;

/**
 * Get the list of ordered steps that need to be processed to bootstrap EE.
 *
 * Each entry is a fully qualified class name for a class implementing the
 * `EE\Bootstrap\BootstrapStep` interface.
 *
 * @return string[]
 */
function get_bootstrap_steps() {
	return array(
		'EE\Bootstrap\ConfigureRunner',
		'EE\Bootstrap\InitializeColorization',
		'EE\Bootstrap\InitializeLogger',
		'EE\Bootstrap\DefineProtectedCommands',
		'EE\Bootstrap\LoadRequiredCommand',
		'EE\Bootstrap\IncludePackageAutoloader',
		'EE\Bootstrap\RegisterDeferredCommands',
		'EE\Bootstrap\LaunchRunner',
	);
}

/**
 * Initialize and return the bootstrap state to pass from step to step.
 *
 * @return BootstrapState
 */
function initialize_bootstrap_state() {
	return new BootstrapState();
}

/**
 * Process the bootstrapping steps.
 *
 * Loops over each of the provided steps, instantiates it and then calls its
 * `process()` method.
 */
function bootstrap() {

	require_once EE_VENDOR_DIR . '/autoload.php';
	$state = initialize_bootstrap_state();

	foreach ( get_bootstrap_steps() as $step ) {
		/** @var \EE\Bootstrap\BootstrapStep $step_instance */
		$step_instance = new $step();
		$state = $step_instance->process( $state );
	}
}
