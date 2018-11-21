<?php

namespace EE\Bootstrap;

/**
 * Class IncludeBundledAutoloader.
 *
 * Loads the bundled autoloader that is provided through the `composer.json`
 * file.
 *
 * This only contains classes for the commands.
 *
 * @package EE\Bootstrap
 */
final class IncludeBundledAutoloader extends AutoloaderStep {

	/**
	 * Get the autoloader paths to scan for an autoloader.
	 *
	 * @return string[]|false Array of strings with autoloader paths, or false
	 *                        to skip.
	 */
	protected function get_autoloader_paths() {
		$autoloader_paths = array(
			EE_VENDOR_DIR . '/autoload_commands.php',
		);

		if ( $custom_vendor = $this->get_custom_vendor_folder() ) {
			array_unshift(
				$autoloader_paths,
				EE_ROOT . '/../../../' . $custom_vendor . '/autoload_commands.php'
			);
		}

		return $autoloader_paths;
	}
}
