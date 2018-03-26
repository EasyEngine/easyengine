<?php

use Behat\Behat\Context\BehatContext,
	Behat\Behat\Exception\PendingException;

use Behat\Gherkin\Node\PyStringNode,
	Behat\Gherkin\Node\TableNode,
	Behat\Gherkin\Node\OutlineNode;

use Behat\Behat\Event\FeatureEvent;

class FeatureContext extends BehatContext implements \Behat\Behat\Context\ClosuredContextInterface {
	public $output;
	public $webroot_path;

	/**
	 * Returns array of step definition files (*.php).
	 *
	 * @return array
	 */
	public function getStepDefinitionResources() {
		return glob( __DIR__ . '/../steps/*.php' );
	}

	/**
	 * Returns array of hook definition files (*.php).
	 *
	 * @return array
	 */
	public function getHookDefinitionResources() {
		return array();
	}

	/**
	 * @AfterFeature
	 */
	public static function cleanup( FeatureEvent $event ) {
		$sites = ( array_column( array_slice( $event->getFeature()->getScenarios()[1]->getExamples()->getRows(), 1 ), 0 ) );
		$out   = shell_exec( "bin/ee site list" );

		foreach ( $sites as $site ) {
			if ( strpos( $out, $site ) !== false ) {
				exec( "bin/ee site delete $site" );
			}
		}
	}
}
