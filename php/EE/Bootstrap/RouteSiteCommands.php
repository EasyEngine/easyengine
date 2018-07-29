<?php

namespace EE\Bootstrap;

/**
 * Class LaunchRunner.
 *
 * Kick off the Runner object that starts the actual commands.
 *
 * @package EE\Bootstrap
 */
final class RouteSiteCommands implements BootstrapStep {

	/**
	 * Process this single bootstrapping step.
	 *
	 * @param BootstrapState $state Contextual state to pass into the step.
	 *
	 * @return BootstrapState Modified state to pass to the next step.
	 */
	public function process( BootstrapState $state ) {
		$runner = new RunnerInstance();
		$runner()->route();

		return $state;
	}
}
