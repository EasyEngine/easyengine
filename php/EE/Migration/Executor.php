<?php

namespace EE\Migration;

use \EE;
use \EE\Model\Migration;
use \EE\Utils;
use Symfony\Component\Finder\Finder;

class Executor {

	/**
	 * Executes all pending migrations
	 */
	public static function execute_migrations() {

		Utils\delem_log( 'ee migration start' );
		EE::log( 'Migrating EasyEngine data to new version' );

		$migration_paths = self::get_migration_paths();

		if ( empty( $migration_paths ) ) {
			EE::success( 'Nothing to migrate' );
			exit( 0 );
		}

		$migrations = [];

		foreach ( $migration_paths as $package_path ) {
			$migrations[] = self::get_migrations_to_execute( $package_path );
		}

		$migrations = array_merge( ...$migrations );

		if ( empty( $migrations ) ) {
			EE::success( 'Nothing to migrate' );
		}

		sort( $migrations );

		try {
			self::execute_migration_stack( $migrations );
		} catch ( \Throwable $e ) {
			Utils\delem_log( 'ee migration ended abruptly' );
			exit( 1 );
		}

		EE::success( 'Successfully migrated EasyEngine' );
	}

	/**
	 * @return array of available migration paths
	 */
	private static function get_migration_paths() {

		$migration_paths = glob( EE_ROOT . '/vendor/easyengine/*/migrations' );
		$ee_path         = glob( EE_ROOT . '/migrations' );

		// set migration path for easyengine.
		if ( ! empty( $ee_path ) ) {
			$migration_paths[] = $ee_path[0];
		}
		return $migration_paths;
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
			EE::log( "Migrating: $migrations[0]" );
			$migration->up();

			Migration::create( [
				'migration' => $migrations[0],
				'timestamp' => date( 'Y-m-d H:i:s' ),
			] );

			$migration->status = 'complete';
			EE::log( "Migrated: $migrations[0]" );
			$remaining_migrations = array_splice( $migrations, 1, count( $migrations ) );
			self::execute_migration_stack( $remaining_migrations );
		} catch ( \Throwable $e ) {
			if ( 'complete' !== $migration->status ) {
				EE::error( "Errors were encountered while processing: $migrations[0]\n" . $e->getMessage(), false );
			}
			EE::log( "Reverting: $migrations[0]" );
			$migration->down();
			EE::log( "Reverted: $migrations[0]" );
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
	private static function get_migrations_to_execute( $path ) {
		return array_values(
			array_diff(
				self::get_migrations_from_fs( $path ),
				self::get_migrations_from_db()
			)
		);
	}

	/**
	 * Get already migrated migrations.
	 *
	 * @return array
	 */
	private static function get_migrations_from_db() {
		return Migration::get_migrations();
	}

	/**
	 * Get migrations from filesystem.
	 *
	 * @param $path path to the migrations on filesystem.
	 *
	 * @return array
	 */
	private static function get_migrations_from_fs( $path ) {
		// array_slice is used to remove . and .. returned by scandir()
		$migrations = array_slice( scandir( $path ), 2 );
		array_walk( $migrations, function ( &$migration, $index ) {
			$migration = rtrim( $migration, '.php' );
		} );
		return $migrations;
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

		if ( 'easyengine' === $matches[1] ) {
			return EE_ROOT . "/migrations/$migration_name.php";
		} else {
			return EE_ROOT . "/vendor/easyengine/$matches[1]/migrations/$migration_name.php";
		}

	}

	private static function get_migration_class_name( $migration_name ) {
		// Remove date and package name from it
		$class_name = preg_replace( '/(^\d*)[_]([a-zA-Z-]*[_])/', '', $migration_name );
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
