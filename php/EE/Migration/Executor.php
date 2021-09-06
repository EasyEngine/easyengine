<?php

namespace EE\Migration;

use EE;
use EE\Model\Migration;
use EE\Utils;
use Symfony\Component\Finder\Finder;

class Executor {

	/**
	 * Executes all pending migrations
	 */
	public static function execute_migrations() {

		Utils\delem_log( 'ee migration start' );
		EE::debug( 'Executing migrations' );

		$migrations = self::get_all_migrations();

		if ( empty( $migrations ) ) {
			EE::debug( 'Nothing to migrate' );
			return;
		}

		sort( $migrations );

		try {
			self::execute_migration_stack( $migrations );
		} catch ( \Throwable $e ) {
			Utils\delem_log( 'ee migration ended abruptly' );
			exit( 1 );
		}

		EE::debug( 'Successfully migrated EasyEngine' );
	}

	/**
	 * @return array of available migrations
	 */
	private static function get_all_migrations() {
		$migrations    = [];
		$packages_path = scandir( EE_VENDOR_DIR . '/easyengine' );

		// get migrations from packages.
		if ( ! empty( $packages_path ) ) {
			foreach ( $packages_path as $package ) {
				if ( '.' === $package || '..' === $package || is_file( $package ) ) {
					continue;
				}

				$migration_path = EE_VENDOR_DIR . '/easyengine/' . $package . '/migrations/db';
				if ( is_dir( $migration_path ) ) {
					$files = scandir( $migration_path );
					if ( \EE\Utils\inside_phar() ) {
						$migrations[] = $files;
					} else {
						$migrations[] = array_slice( $files, 2 );
					}
				}
			}
		}

		// get migrations from core.
		if ( is_dir( EE_ROOT . '/migrations' ) ) {
			$files = scandir( EE_ROOT . '/migrations/db' );
			if ( \EE\Utils\inside_phar() ) {
				$migrations[] = $files;
			} else {
				$migrations[] = array_slice( $files, 2 );
			}
		}

		if ( ! empty( $migrations ) ) {
			$migrations = array_merge( ...$migrations );
		}

		$migrations = self::get_migrations_to_execute( $migrations );

		return array_filter( $migrations, function ( $file_name ) {
			if ( preg_match( '/^\d*[_]([a-zA-Z-]*)[_].*(\.php)$/', $file_name ) ) {
				return true;
			}
			return false;
		} );
	}

	/**
	 * Executes all migrations passed to it recursively.
	 * Also undo'es all migration if there was error executing any migration
	 */
	private static function execute_migration_stack( $migrations ) {
		if ( empty( $migrations ) ) {
			return;
		}

		$migration_path       = self::get_migration_path( $migrations[0] );
		$migration_class_name = self::get_migration_class_name( $migrations[0] );

		if ( ! file_exists( $migration_path ) ) {
			EE::error( "Unable to find migration file at $migration_path", false );
			throw new Exception();
		}

		require( $migration_path );

		try {
			$migration = new $migration_class_name;
			if ( ! $migration instanceof Base ) {
				throw new \Exception( "$migration_class_name is not a instance of base migration class" );
			}
		} catch ( \Throwable $e ) {
			EE::error( $e->getMessage(), false );
			throw $e;
		}

		try {
			EE::debug( "Migrating: $migrations[0]" );
			$migration->up();

			Migration::create( [
				'migration' => $migrations[0],
				'type'      => 'db',
				'timestamp' => date( 'Y-m-d H:i:s' ),
			] );

			$migration->status = 'complete';
			EE::debug( "Migrated: $migrations[0]" );
			$remaining_migrations = array_splice( $migrations, 1, count( $migrations ) );
			self::execute_migration_stack( $remaining_migrations );
		} catch ( \Throwable $e ) {
			if ( 'complete' !== $migration->status ) {
				EE::error( "Errors were encountered while processing: $migrations[0]\n" . $e->getMessage(), false );
			}
			EE::debug( "Reverting: $migrations[0]" );
			// remove db entry in 'migration' table when reverting migrations.
			$migrated = Migration::where( 'migration', $migrations[0] );
			if ( ! empty( $migrated ) ) {
				$migration->down();
				$migrated[0]->delete();
			}

			EE::debug( "Reverted: $migrations[0]" );
			throw $e;
		}
	}

	/**
	 *  Get migrations need to be executed.
	 *
	 * @param $path path to the migration directory.
	 *
	 * @return array
	 */
	private static function get_migrations_to_execute( $migrations ) {
		return array_values(
			array_diff(
				$migrations,
				self::get_migrations_from_db()
			)
		);
	}

	/**
	 * Get already migrated migrations from database.
	 *
	 * @return array
	 */
	private static function get_migrations_from_db() {
		return array_column( Migration::where( 'type', 'db' ), 'migration' );
	}

	/**
	 * Get path of the migration file.
	 *
	 * @param $migration_name name of a migration file.
	 *
	 * @return string path of a migration file.
	 */
	private static function get_migration_path( $migration_name ) {
		preg_match( '/^\d*[_]([a-zA-Z-]*)[_]/', $migration_name, $matches );

		if ( empty( $matches[1] ) ) {
			return '';
		}
		if ( 'easyengine' === $matches[1] ) {
			return EE_ROOT . "/migrations/db/$migration_name";
		} else {
			return EE_ROOT . "/vendor/easyengine/$matches[1]/migrations/db/$migration_name";
		}

	}

	private static function get_migration_class_name( $migration_name ) {
		// Remove date and package name from it
		$class_name = preg_replace( '/(^\d*)[_]([a-zA-Z-]*[_])/', '', rtrim( $migration_name, '.php' ) );
		// Convet snake_case to CamelCase
		$class_name = self::camelize( $class_name );
		// Replace dot with underscore
		$class_name = str_replace( '.', '_', $class_name );

		return "\EE\Migration\\$class_name";
	}

	private static function camelize( $input, $separator = '_' ) {
		return str_replace( $separator, '', ucwords( $input, $separator ) );
	}
}
