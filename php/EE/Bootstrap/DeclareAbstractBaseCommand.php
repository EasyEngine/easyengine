<?php

namespace EE\Bootstrap;

/**
 * Class DeclareAbstractBaseCommand.
 *
 * Declares the abstract `EE_Command` base class.
 *
 * @package EE\Bootstrap
 */
final class DeclareAbstractBaseCommand implements BootstrapStep {

	/**
	 * Process this single bootstrapping step.
	 *
	 * @param BootstrapState $state Contextual state to pass into the step.
	 *
	 * @return BootstrapState Modified state to pass to the next step.
	 */
	public function process( BootstrapState $state ) {
		require_once EE_ROOT . '/php/class-ee-command.php';

		return $state;
	}
}
