<?php

namespace EE\Bootstrap;

/**
 * Class RunnerInstance.
 *
 * Convenience class for steps that make use of the `EE\Runner` object.
 *
 * @package EE\Bootstrap
 */
final class RunnerInstance {

	/**
	 * Return an instance of the `EE\Runner` object.
	 *
	 * Includes necessary class files first as needed.
	 *
	 * @return \EE\Runner
	 */
	public function __invoke() {
		if ( ! class_exists( 'EE\Runner' ) ) {
			require_once EE_ROOT . '/php/EE/Runner.php';
		}

		if ( ! class_exists( 'EE\Configurator' ) ) {
			require_once EE_ROOT . '/php/EE/Configurator.php';
		}

		return \EE::get_runner();
	}
}
