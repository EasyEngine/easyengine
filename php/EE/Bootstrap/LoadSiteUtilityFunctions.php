<?php

namespace EE\Bootstrap;

/**
 * Class LoadSiteUtilityFunctions.
 *
 * Loads the functions available through `EE\SiteUtils`.
 *
 * @package EE\Bootstrap
 */
final class LoadSiteUtilityFunctions implements BootstrapStep {

	/**
	 * Process this single bootstrapping step.
	 *
	 * @param BootstrapState $state Contextual state to pass into the step.
	 *
	 * @return BootstrapState Modified state to pass to the next step.
	 */
	public function process( BootstrapState $state ) {
		require_once EE_ROOT . '/php/site-utils.php';

		return $state;
	}
}
