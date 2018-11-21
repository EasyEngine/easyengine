<?php

namespace EE\Bootstrap;

/**
 * Class InitializeLogger.
 *
 * Initialize the logger through the `EE\Runner` object.
 *
 * @package EE\Bootstrap
 */
final class InitializeLogger implements BootstrapStep {

	/**
	 * Process this single bootstrapping step.
	 *
	 * @param BootstrapState $state Contextual state to pass into the step.
	 *
	 * @return BootstrapState Modified state to pass to the next step.
	 */
	public function process( BootstrapState $state ) {
		$this->declare_loggers();
		$runner = new RunnerInstance();
		$runner()->init_logger();

		return $state;
	}

	/**
	 * Load the class declarations for the loggers.
	 */
	private function declare_loggers() {
		$logger_dir = EE_ROOT . '/php/EE/Loggers';
		$iterator   = new \DirectoryIterator( $logger_dir );

		// Make sure the base class is declared first.
		include_once "$logger_dir/Base.php";

		foreach ( $iterator as $filename ) {
			if ( '.php' !== substr( $filename, - 4 ) ) {
				continue;
			}

			include_once "$logger_dir/$filename";
		}
	}
}
