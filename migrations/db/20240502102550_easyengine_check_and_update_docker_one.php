<?php

namespace EE\Migration;

use EE;
use EE\Migration\Base;
use Symfony\Component\Filesystem\Filesystem;

class CheckAndUpdateDockerOne extends Base {

	public function __construct() {

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
		$docker_compose_version     = trim( EE::launch( 'docker-compose version --short' )->stdout );
		$docker_compose_path        = EE::launch( 'command -v docker-compose' )->stdout;
		$docker_compose_path        = trim( $docker_compose_path );
		$docker_compose_backup_path = EE_BACKUP_DIR . '/docker-compose.backup';
		$docker_compose_new_path    = EE_BACKUP_DIR . '/docker-compose';
		$fs                         = new Filesystem();
		if ( ! $fs->exists( EE_BACKUP_DIR ) ) {
			$fs->mkdir( EE_BACKUP_DIR );
		}
		$fs->copy( $docker_compose_path, $docker_compose_backup_path );

		if ( version_compare( '1.29.2', $docker_compose_version, '!=' ) ) {
			EE::exec( "curl -L https://github.com/docker/compose/releases/download/1.29.2/docker-compose-$(uname -s)-$(uname -m) -o $docker_compose_new_path && chmod +x $docker_compose_new_path" );
			EE::exec( "mv $docker_compose_new_path $docker_compose_path" );
		}

		// Check the version again post update.
		$docker_version = trim( EE::launch( 'docker version --format "{{.Server.Version}}"' )->stdout );
		if ( version_compare( $docker_version, '20.10.10', '<' ) ) {
			EE::error( 'Docker version should be 20.10.10 or above. Please update Docker and try again.' );
		}

		$docker_compose_version = trim( EE::launch( 'docker-compose version --short' )->stdout );
		if ( version_compare( '1.29.2', $docker_compose_version, '!=' ) ) {
			EE::error( 'Docker-compose version should be 1.29.2. Please update Docker-compose and try again.' );
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
