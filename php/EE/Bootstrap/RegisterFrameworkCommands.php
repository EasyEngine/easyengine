<?php

namespace EE\Bootstrap;

/**
 * Class RegisterFrameworkCommands.
 *
 * Register the commands that are directly included with the framework.
 *
 * @package EE\Bootstrap
 */
final class RegisterFrameworkCommands implements BootstrapStep {

	/**
	 * Process this single bootstrapping step.
	 *
	 * @param BootstrapState $state Contextual state to pass into the step.
	 *
	 * @return BootstrapState Modified state to pass to the next step.
	 */
	public function process( BootstrapState $state ) {
		$cmd_dir = EE_ROOT . '/php/commands';

		$iterator = new \DirectoryIterator( $cmd_dir );

		foreach ( $iterator as $filename ) {
			if ( '.php' !== substr( $filename, - 4 ) ) {
				continue;
			}

			try {
				include_once "$cmd_dir/$filename";
			} catch ( \Exception $exception ) {
				\EE::warning(
					"Could not add command {$cmd_dir}/{$filename}. Reason: " . $exception->getMessage()
				);
			}
		}

		return $state;
	}
}
