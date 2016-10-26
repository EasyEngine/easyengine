<?php

/**
 * Database model for site table.
 *
 * Class EE_Sqlite_Db
 */
class EE_Sqlite_Db {

	public static $ee_site_table_name = "sites";

	/**
	 * Database connection with sqlite database.
	 *
	 * @return bool|SQLite3
	 */
	public static function dbConnection() {
		//		$ee_db_file = EE_Variables::get_ee_db_file();
		// TODO: Test db on root dir of project.
		$ee_db_file = EE_ROOT . '/ee.db';

		$ee_db = new SQLite3( $ee_db_file );
		if ( ! $ee_db ) {
			EE::debug( $ee_db->lastErrorMsg() );

			return false;
		}

		return $ee_db;
	}

	/**
	 * Create sqlite database table. (Default: create site table in ee db)
	 *
	 * @param string $ee_db
	 *
	 * @return bool
	 */
	public static function createDb( $ee_db = '' ) {
		if ( empty( $ee_db ) ) {
			$ee_db = self::dbConnection();
		}
		if ( false === $ee_db ) {
			return false;
		}
		$is_table_exist = self::is_site_table_exist( $ee_db );
		if ( ! $is_table_exist ) {
			$ee_table_name    = self::$ee_site_table_name;
			$create_table_sql = "CREATE TABLE {$ee_table_name} (
									id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, 
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

	/**
	 * Check if table is already exist or not in database.
	 *
	 * @param        $table_name
	 * @param string $ee_db
	 *
	 * @return bool
	 */
	public static function is_table_exist( $table_name, $ee_db = '' ) {
		if ( empty( $ee_db ) ) {
			$ee_db = self::dbConnection();
		}
		$table_exist_sql  = "SELECT name FROM sqlite_master WHERE type='table' AND name='{$table_name}'";
		$table_exist_exec = $ee_db->query( $table_exist_sql );
		$table_exist_name = $table_exist_exec->fetchArray( SQLITE3_ASSOC );

		if ( empty( $table_exist_name ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Check if ee site table is exist or not in ee db.
	 *
	 * @param string $ee_db
	 *
	 * @return bool
	 */
	public static function is_site_table_exist( $ee_db = '' ) {
		if ( empty( $ee_db ) ) {
			$ee_db = self::dbConnection();
		}
		$is_table_exist = self::is_table_exist( self::$ee_site_table_name, $ee_db );

		return $is_table_exist;
	}

	/**
	 * Filter data array with site table columns to avoid extra fields.
	 *
	 * @param $data
	 *
	 * @return array
	 */
	public static function filter_ee_data_fields( $data ) {
		// For backward compatibility.
		if ( ! empty( $data['site_name'] ) ) {
			$data['sitename'] = $data['site_name'];
		}

		if ( ! empty( $data['ee_db_name'] ) ) {
			$data['db_name'] = $data['ee_db_name'];
		}

		if ( ! empty( $data['ee_db_user'] ) ) {
			$data['db_user'] = $data['ee_db_user'];
		}

		if ( ! empty( $data['ee_db_pass'] ) ) {
			$data['db_password'] = $data['ee_db_pass'];
		}

		if ( ! empty( $data['ee_db_host']) ) {
			$data['db_host'] = $data['ee_db_host'];
	    }

		$ee_db_fields = array(
			'id',
			'sitename',
			'site_type',
			'cache_type',
			'site_path',
			'created_on',
			'is_enabled',
			'is_ssl',
			'storage_fs',
			'storage_db',
			'db_name',
			'db_user',
			'db_password',
			'db_host',
			'is_hhvm',
			'is_pagespeed',
			'php_version'
		);

		$filter_data = array();

		foreach ( $ee_db_fields as $ee_db_field ) {
			if ( isset( $data[ $ee_db_field ] ) && is_bool( $data[ $ee_db_field ] ) ) {
				$filter_data[ $ee_db_field ] = (int) $data[ $ee_db_field ];
			} else if ( ! empty( $data[ $ee_db_field ] ) ) {
				$filter_data[ $ee_db_field ] = $data[ $ee_db_field ];
			}
		}

		return $filter_data;
	}

	/**
	 * Insert row in table.
	 *
	 * @param        $data
	 * @param string $table_name
	 * @param string $ee_db
	 *
	 * @return bool
	 */
	public static function insert( $data, $table_name = '', $ee_db = '' ) {
		$data = self::filter_ee_data_fields( $data );
		if ( empty( $ee_db ) ) {
			$ee_db = self::dbConnection();
		}
		if ( false === $ee_db ) {
			return false;
		}
		if ( empty( $table_name ) ) {
			$table_name = self::$ee_site_table_name;
		}
		$is_table_exist = self::is_site_table_exist( $ee_db );
		if ( $is_table_exist ) {
			$fields  = '`' . implode( '`, `', array_keys( $data ) ) . '`';
			$formats = '"' . implode( '", "', $data ) . '"';

			$insert_query = "INSERT INTO `$table_name` ($fields) VALUES ($formats);";

			$insert_query_exec = $ee_db->exec( $insert_query );

			if ( ! $insert_query_exec ) {
				EE::debug( $ee_db->lastErrorMsg() );
				$ee_db->close();
			} else {
				$ee_db->close();

				return true;
			}
		}

		return false;
	}

	/**
	 * Update row in table.
	 *
	 * @param        $data
	 * @param        $where
	 * @param string $table_name
	 * @param string $ee_db
	 *
	 * @return bool
	 */
	public static function update( $data, $where, $table_name = '', $ee_db = '' ) {
		// Remove/Filter extra fields if it passed in $data array.
		$data = self::filter_ee_data_fields( $data );
		// Remove/Filter extra fields if it passed in $where array.
		$where = self::filter_ee_data_fields( $where );
		if ( empty( $ee_db ) ) {
			$ee_db = self::dbConnection();
		}
		if ( false === $ee_db ) {
			return false;
		}
		if ( empty( $table_name ) ) {
			$table_name = self::$ee_site_table_name;
		}
		$is_table_exist = self::is_site_table_exist( $ee_db );
		if ( $is_table_exist ) {
			$fields     = array();
			$conditions = array();
			foreach ( $data as $key => $value ) {
				$fields[] = "`$key`='" . $value . "'";
			}

			foreach ( $where as $key => $value ) {
				$conditions[] = "`$key`='" . $value . "'";
			}

			$fields     = implode( ', ', $fields );
			$conditions = implode( ' AND ', $conditions );

			$update_query = "UPDATE `$table_name` SET $fields WHERE $conditions";

			$update_query_exec = $ee_db->exec( $update_query );

			if ( ! $update_query_exec ) {
				EE::debug( $ee_db->lastErrorMsg() );
				$ee_db->close();
			} else {
				$ee_db->close();

				return true;
			}
		}

		return false;
	}

	public static function select( $where = array(), $table_name = '', $columns = array(), $ee_db = '' ) {
		// Remove/Filter extra fields if it passed in $where array.
		$where   = self::filter_ee_data_fields( $where );
		// Remove/Filter extra fields if it passed in $columns array.
		$columns = self::filter_ee_data_fields( $columns );
		if ( empty( $ee_db ) ) {
			$ee_db = self::dbConnection();
		}
		if ( false === $ee_db ) {
			return false;
		}
		if ( empty( $table_name ) ) {
			$table_name = self::$ee_site_table_name;
		}

		$conditions = array();
		if ( empty( $columns ) ) {
			$columns = '*';
		} else {
			$columns = implode( ', ', $columns );
		}

		foreach ( $where as $key => $value ) {
			$conditions[] = "`$key`='" . $value . "'";
		}

		$conditions = implode( ' AND ', $conditions );

		$select_data_query = "SELECT {$columns} FROM `$table_name`";

		if ( ! empty( $conditions ) ) {
			$select_data_query .= " WHERE $conditions";
		}

		$select_data_exec = $ee_db->query( $select_data_query );
		$select_data      = array();
		while ( $row = $select_data_exec->fetchArray( SQLITE3_ASSOC ) ) {
			$select_data[] = $row;
		}
		if ( empty( $select_data ) ) {
			return false;
		}

		return $select_data;
	}

	/**
	 * Delete data from table.
	 *
	 * @param        $where
	 * @param string $table_name
	 * @param string $ee_db
	 *
	 * @return bool
	 */
	public static function delete( $where, $table_name = '', $ee_db = '' ) {
		// Remove/Filter extra fields if it passed in $where array.
		$where = self::filter_ee_data_fields( $where );
		if ( empty( $ee_db ) ) {
			$ee_db = self::dbConnection();
		}

		if ( false === $ee_db ) {
			return false;
		}

		if ( empty( $table_name ) ) {
			$table_name = self::$ee_site_table_name;
		}

		$conditions = array();
		foreach ( $where as $key => $value ) {
			$conditions[] = "`$key`='" . $value . "'";
		}

		$conditions   = implode( ' AND ', $conditions );
		$delete_query = "DELETE FROM `$table_name` WHERE $conditions";

		$delete_query_exec = $ee_db->exec( $delete_query );

		if ( ! $delete_query_exec ) {
			EE::debug( $ee_db->lastErrorMsg() );
			$ee_db->close();
		} else {
			$ee_db->close();

			return true;
		}

		return false;
	}


}