<?php

namespace EE\Bootstrap;

use EE\Autoloader;

/**
 * Class IncludeFrameworkAutoloader.
 *
 * Loads the framework autoloader through an autoloader separate from the
 * Composer one, to avoid coupling the loading of the framework with bundled
 * commands.
 *
 * This only contains classes for the framework.
 *
 * @package EE\Bootstrap
 */
final class IncludeFrameworkAutoloader implements BootstrapStep {

	/**
	 * Process this single bootstrapping step.
	 *
	 * @param BootstrapState $state Contextual state to pass into the step.
	 *
	 * @return BootstrapState Modified state to pass to the next step.
	 */
	public function process( BootstrapState $state ) {
		if ( ! class_exists( 'EE\Autoloader' ) ) {
			require_once EE_ROOT . '/php/EE/Autoloader.php';
		}

		$autoloader = new Autoloader();

		$mappings = [
			'EE'                       => EE_ROOT . '/php/EE',
			'cli'                      => EE_VENDOR_DIR . '/wp-cli/php-cli-tools/lib/cli',
			'WpOrg\\Requests'          => EE_VENDOR_DIR . '/rmccue/requests/src', // New PSR-4 mapping
			'Symfony\Component\Finder' => EE_VENDOR_DIR . '/symfony/finder/',
			'Psr\Log'                  => EE_VENDOR_DIR . '/psr/log/Psr/Log/',
			'Monolog'                  => EE_VENDOR_DIR . '/monolog/monolog/src/Monolog',
		];

		foreach ( $mappings as $namespace => $folder ) {
			$autoloader->add_namespace(
				$namespace,
				$folder
			);
		}

		include_once EE_VENDOR_DIR . '/wp-cli/mustangostang-spyc/Spyc.php';

		$autoloader->register();

		return $state;
	}
}
