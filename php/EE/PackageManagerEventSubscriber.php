<?php

namespace EE;

use \Composer\DependencyResolver\Rule;
use \Composer\EventDispatcher\EventSubscriberInterface;
use \Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use \EE;

/**
 * A Composer Event subscriber so we can keep track of what's happening inside Composer
 */
class PackageManagerEventSubscriber implements EventSubscriberInterface {

	public static function getSubscribedEvents() {

		return array(
			PackageEvents::PRE_PACKAGE_INSTALL => 'pre_install',
			PackageEvents::POST_PACKAGE_INSTALL => 'post_install',
		);
	}

	public static function pre_install( PackageEvent $event ) {
		$operation_message = $event->getOperation()->__toString();
		EE::log( ' - ' . $operation_message );
	}

	public static function post_install( PackageEvent $event ) {

		$operation = $event->getOperation();
		$reason = $operation->getReason();
		if ( $reason instanceof Rule ) {

			switch ( $reason->getReason() ) {

				case Rule::RULE_PACKAGE_CONFLICT:
				case Rule::RULE_PACKAGE_SAME_NAME:
				case Rule::RULE_PACKAGE_REQUIRES:
					$composer_error = $reason->getPrettyString( $event->getPool() );
					break;

			}

			if ( ! empty( $composer_error ) ) {
				EE::log( sprintf( " - Warning: %s", $composer_error ) );
			}
		}

	}

}
