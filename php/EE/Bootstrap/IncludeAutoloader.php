<?php

namespace EE\Bootstrap;

/**
 * Class IncludeAutoloader.
 *
 * Loads the autoloader that is provided through the `composer.json`
 * file.
 *
 * @package EE\Bootstrap
 */
final class IncludeAutoloader extends AutoloaderStep {

	/**
	 * Get the autoloader paths to scan for an autoloader.
	 *
	 * @return string[]|false Array of strings with autoloader paths, or false
	 *                        to skip.
	 */
	protected function get_autoloader_paths() {
		$autoloader_paths = array(
			EE_VENDOR_DIR . '/autoload.php',
		);

		if ( $custom_vendor = $this->get_custom_vendor_folder() ) {
			array_unshift(
				$autoloader_paths,
				EE_ROOT . '/../../../' . $custom_vendor . '/autoload.php'
			);
		}

		return $autoloader_paths;
	}
}
