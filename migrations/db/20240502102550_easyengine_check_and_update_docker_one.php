<?php

namespace EE\Migration;

use EE;
use EE\Migration\Base;

class CheckAndUpdateDockerOne extends Base {

	public function __construct() {

		parent::__construct();
		if ( $this->is_first_execution ) {
			$this->skip_this_migration = true;
		}
	}

	/**
	 * Execute create table query for site and sitemeta table.
	 *
	 * @throws EE\ExitException
	 */
	public function up() {

		if ( $this->skip_this_migration ) {
			EE::debug( 'Skipping migration as it is not needed.' );

			return;
		}

		EE::debug( 'Checking Docker version.' );
		$docker_version = trim( EE::launch( 'docker version --format "{{.Server.Version}}"' )->stdout );

		if ( version_compare( $docker_version, '20.10.10', '<' ) ) {
			EE::warning( 'Docker version should be 20.10.10 or above.' );

			// If it is MacOS, prompt user to update docker.
			if ( 'Darwin' === PHP_OS ) {
				EE::confirm( 'Do you want to update Docker?' );
				EE::launch( 'open "docker://"' );
			}

			// If it is Linux, proceed with update.
			if ( 'Linux' === PHP_OS ) {
				EE::debug( 'Updating Docker...' );
				EE::launch( 'curl -fsSL https://get.docker.com | sh' );
			}
		}

		EE::debug( 'Checking docker-compose version' );
		// Installs only when docker-compose is missing or too old; it never downgrades.
		Containers::update_docker_compose();

		// Check the version again post update.
		$docker_version = trim( EE::launch( 'docker version --format "{{.Server.Version}}"' )->stdout );
		if ( version_compare( $docker_version, '20.10.10', '<' ) ) {
			EE::error( 'Docker version should be 20.10.10 or above. Please update Docker and try again.' );
		}
	}

	/**
	 * Execute drop table query for site and sitemeta table.
	 *
	 * @throws EE\ExitException
	 */
	public function down() {

	}
}
