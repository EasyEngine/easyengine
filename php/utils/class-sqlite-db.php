<?php

/**
 * Database model for site table.
 *
 * Class EE_Sqlite_Db
 */
class EE_Sqlite_Db {

	public static function createDb() {
		//		$ee_db_file        = EE_Variables::get_ee_db_file();
		$ee_table_name = "sites";
		// TODO: Test db on root dir of project.
		$ee_repo_file_path = EE_ROOT . '/ee.db';
		$ee_db_file        = $ee_repo_file_path;
		$ee_db             = new SQLite3( $ee_db_file );
		if ( ! $ee_db ) {
			EE::debug( $ee_db->lastErrorMsg() );
		}
		$is_table_exist = self::is_table_exist( $ee_db, $ee_table_name );
		if ( ! $is_table_exist ) {
			$create_table_sql = "CREATE TABLE {$ee_table_name} (
									id INTEGER NOT NULL, 
									sitename VARCHAR, 
									site_type VARCHAR, 
									cache_type VARCHAR, 
									site_path VARCHAR, 
									created_on DATETIME, 
									is_enabled BOOLEAN NOT NULL, 
									is_ssl BOOLEAN, 
									storage_fs VARCHAR, 
									storage_db VARCHAR, 
									db_name VARCHAR, 
									db_user VARCHAR, 
									db_password VARCHAR, 
									db_host VARCHAR, 
									is_hhvm BOOLEAN, 
									is_pagespeed BOOLEAN, 
									php_version VARCHAR, 
									PRIMARY KEY (id), 
									UNIQUE (sitename), 
									CHECK (is_enabled IN (0, 1)), 
									CHECK (is_ssl IN (0, 1)), 
									CHECK (is_hhvm IN (0, 1)), 
									CHECK (is_pagespeed IN (0, 1))
								);";

			$create_table_exec = $ee_db->exec( $create_table_sql );
			if ( ! $create_table_exec ) {
				EE::debug( $ee_db->lastErrorMsg() );
			}
		}
		$ee_db->close();
	}

	public static function is_table_exist( $ee_db, $table_name ) {
		$table_exist_sql  = "SELECT name FROM sqlite_master WHERE type='table' AND name='{$table_name}'";
		$table_exist_exec = $ee_db->exec( $table_exist_sql );
		if ( ! $table_exist_exec ) {
			return false;
		}

		return true;
	}
}