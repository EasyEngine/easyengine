<?php

namespace EE\Bootstrap;

/**
 * Class DeclareMainClass.
 *
 * Declares the main `EE` class.
 *
 * @package EE\Bootstrap
 */
final class DeclareMainClass implements BootstrapStep {

	/**
	 * Process this single bootstrapping step.
	 *
	 * @param BootstrapState $state Contextual state to pass into the step.
	 *
	 * @return BootstrapState Modified state to pass to the next step.
	 */
	public function process( BootstrapState $state ) {
		require_once EE_ROOT . '/php/class-ee.php';

		return $state;
	}
}
