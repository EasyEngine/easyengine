<?php

namespace EE\Bootstrap;

/**
 * Class ConfigureRunner.
 *
 * Initialize the configuration for the `EE\Runner` object.
 *
 * @package EE\Bootstrap
 */
final class ConfigureRunner implements BootstrapStep {

	/**
	 * Process this single bootstrapping step.
	 *
	 * @param BootstrapState $state Contextual state to pass into the step.
	 *
	 * @return BootstrapState Modified state to pass to the next step.
	 */
	public function process( BootstrapState $state ) {
		$runner = new RunnerInstance();
		$runner()->init_config();

		return $state;
	}
}
