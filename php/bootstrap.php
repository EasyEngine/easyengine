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
		'EE\Bootstrap\LoadUtilityFunctions',
		'EE\Bootstrap\LoadDispatcher',
		'EE\Bootstrap\DeclareMainClass',
		'EE\Bootstrap\DeclareAbstractBaseCommand',
		'EE\Bootstrap\IncludeFrameworkAutoloader',
		'EE\Bootstrap\InitializeColorization',
		'EE\Bootstrap\InitializeLogger',
		'EE\Bootstrap\ConfigureRunner',
		'EE\Bootstrap\DefineProtectedCommands',
		'EE\Bootstrap\LoadRequiredCommand',
		'EE\Bootstrap\IncludePackageAutoloader',
		'EE\Bootstrap\IncludeBundledAutoloader',
		'EE\Bootstrap\RegisterFrameworkCommands',
		'EE\Bootstrap\IncludeFallbackAutoloader',
		'EE\Bootstrap\RegisterDeferredCommands',
		'EE\Bootstrap\LaunchRunner',
	);
}

/**
 * Register the classes needed for the bootstrap process.
 *
 * The Composer autoloader is not active yet at this point, so we need to use a
 * custom autoloader to fetch the bootstrap classes in a flexible way.
 */
function prepare_bootstrap() {
	require_once EE_ROOT . '/php/EE/Autoloader.php';

	$autoloader = new Autoloader();

	$autoloader->add_namespace(
		'EE\Bootstrap',
		EE_ROOT . '/php/EE/Bootstrap'
	)->register();
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
	prepare_bootstrap();
	$state = initialize_bootstrap_state();

	foreach ( get_bootstrap_steps() as $step ) {
		/** @var \EE\Bootstrap\BootstrapStep $step_instance */
		$step_instance = new $step();
		$state = $step_instance->process( $state );
	}
}
