<?php

namespace EE\Bootstrap;

use EE;

/**
 * Class RegisterDeferredCommands.
 *
 * Registers the deferred commands that for which no parent was registered yet.
 * This is necessary, because we can have sub-commands that have no direct
 * parent.
 *
 * @package EE\Bootstrap
 */
final class RegisterDeferredCommands implements BootstrapStep {

	/**
	 * Process this single bootstrapping step.
	 *
	 * @param BootstrapState $state Contextual state to pass into the step.
	 *
	 * @return BootstrapState Modified state to pass to the next step.
	 */
	public function process( BootstrapState $state ) {

		// Process deferred command additions for external packages.
		$this->add_deferred_commands();

		// Process deferred command additions for commands added through
		// plugins.
		EE::add_hook(
			'find_command_to_run_pre',
			array( $this, 'add_deferred_commands' )
		);

		return $state;
	}

	/**
	 * Add deferred commands that are still waiting to be processed.
	 */
	public function add_deferred_commands() {
		$deferred_additions = EE::get_deferred_additions();

		foreach ( $deferred_additions as $name => $addition ) {
			EE::add_command(
				$name,
				$addition['callable'],
				$addition['args']
			);
		}
	}
}
