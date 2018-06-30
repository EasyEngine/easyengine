<?php

namespace EE\Migration;

use \EE;
use \EE\Utils;
use Symfony\Component\Finder\Finder;

class Executor {

    const MIGRATION_PATH = EE_ROOT . '/migrations';

    /**
     * Executes all pending migrations
     */
    public static function execute_migrations() {

        Utils\delem_log( "ee migration start" );
        EE::log( "Migrating EasyEngine data to new version" );

        $migrations = self::get_migrations_to_execute();

        if( empty( $migrations ) ) {
            EE::success( "Noting to migrate" );
            exit( 0 );
        }

        sort( $migrations );

        try {
            self::execute_migration_stack( $migrations );
        } catch( \Throwable $e ) {
            Utils\delem_log( "ee migration ended abruptly" );
            exit( 1 );
        }

        EE::success( "Successfully migrated EasyEngine" );
    }

    /**
     * Executes all migrations passed to it recursively.
     * Also undo'es all migration if there was error executing any migration
     */
    private static function execute_migration_stack( $migrations ) {
        if( empty( $migrations ) ) {
            return;
        }
        
        $migration_path = self::get_migration_path( $migrations[0] );
        $migration_class_name = self::get_migration_class_name( $migrations[0] );
        
        if( ! file_exists( $migration_path ) ) {
            EE::error( "Unable to find migration file at $migration_path", false );
            throw new Exception();
        }

        require( $migration_path );

        try {
            $migration = new $migration_class_name;
            if( ! $migration instanceof Base ) {
                throw new \Exception( "$migration_class_name is not a instance of base migration class" );
            }
        }
        catch( \Throwable $e ) {
            EE::error( $e->getMessage(), false );
            throw $e;
        }

        try {
            EE::log( "Migrating: $migrations[0]" );
            $migration->up();
            
            \EE::db()->insert([
                'migration' => $migrations[0],
                'timestamp' => date('Y-m-d H:i:s')
            ], 'migrations' );
            
            $migration->status = 'complete';
            EE::log( "Migrated: $migrations[0]" );
            $remaining_migrations = array_splice( $migrations, 1, count( $migrations ) );
            self::execute_migration_stack( $remaining_migrations );
        }
        catch( \Throwable $e ) {
            if( $migration->status !== 'complete' ) {
                EE::error( "Errors were encountered while processing: $migrations[0]\n" . $e->getMessage(), false );
            }
            EE::log( "Reverting: $migrations[0]" );
            $migration->down();
            EE::log( "Reverted: $migrations[0]" );
            throw $e;
        }
    }

    private static function get_migrations_to_execute() {
        return array_values( 
            array_diff(
                self::get_migrations_from_fs(),
                self::get_migrations_from_db()
            )
        );
    }

    private static function get_migrations_from_db() {
        return \EE::db()->get_migrations();
    }

    private static function get_migrations_from_fs() {
        // array_slice is used to remove . and .. returned by scandir()
        $migrations = array_slice( scandir( self::MIGRATION_PATH ), 2 );
        array_walk( $migrations, function( &$migration, $index ) {
            $migration = rtrim( $migration, '.php' );
        });
        return $migrations;
    }

    private static function get_migration_path( $migration_name ) {
        return self::MIGRATION_PATH . $migration_name . '.php' ;
    }

    private static function get_migration_class_name( $migration_name ) {
        // Convet snake_case to CamelCase
        $class_name = self::camelize( $migration_name );
        // Replace dot with underscore
        $class_name  = str_replace( '.', '_', $class_name );
        // Remove date from it
        $class_name = preg_replace( '/^\d*(?=[A-Z])/', '', $class_name );

        return "\EE\Migration\\$class_name";
    }

    private static function camelize($input, $separator = '_')
    {
        return str_replace($separator, '', ucwords($input, $separator));
    }
}