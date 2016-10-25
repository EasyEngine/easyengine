<?php

use Symfony\Component\Filesystem\Filesystem;
use NoiseLabs\ToolKit\ConfigParser\ConfigParser;

class EE_MySql {

	/**
	 * Makes connection with MySQL server.
	 */
	public static function connect() {
		try {
			$db_root_username = get_mysql_config( 'client', 'user' );
			$db_root_password = get_mysql_config( 'client', 'password' );
			$mysqli           = new mysqli( 'localhost', $db_root_username, $db_root_password );
			if ( $mysqli->connect_error ) {
				EE::error( 'Connect Error (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error );

				return false;
			}

			return $mysqli;
		} catch ( Exception $e ) {
			EE::debug( $e->getMessage() );
			EE::error( 'Database could not setup.' );

			return false;
		}
	}

	/**
	 * Connect database and return mysqli object.
	 *
	 * @param string $db_name Database name
	 *
	 * @return bool|mysqli
	 */
	public static function dbConnection( $db_name ) {
		try {
			$mysqli = self::connect();
			if ( $mysqli->select_db( $db_name ) ) {
				return $mysqli;
			}
			// TODO: Check if we need create database if not exist;
			//			else if ( $mysqli->query( 'CREATE DATABASE ' . $db_name ) === true ) {
			//				$mysqli->select_db( $db_name );
			//
			//				return $mysqli;
			//			}
		} catch ( mysqli_sql_exception $e ) {
			EE::debug( $e->getMessage() );
			EE::error( 'Database could not setup.' );
		} catch ( Exception $e ) {
			EE::debug( $e->getMessage() );
			EE::error( 'Database could not setup.' );
		}

		return false;
	}

	/**
	 * Get login details from /etc/mysql/conf.d/my.cnf & Execute MySQL query.
	 *
	 * @param string $statement Sql Query to create database, users etc.
	 * @param string $err_msg Log error message if query is fail to execute.
	 * @param bool $log
	 *
	 * @return bool|mysqli
	 */
	public static function execute( $statement, $err_msg = '', $log = true ) {
		$mysqli = self::connect();
		if ( $log ) {
			EE::debug( 'Executing MySQL Statement : ' . $statement );
		}
		try {
			$exec_query = $mysqli->query( $statement );
			if ( ! $exec_query && ! empty( $err_msg ) ) {
				EE::error( $err_msg );
			}
			$mysqli->close();

			return $exec_query;
		} catch ( Exception $e ) {
			EE::debug( $e->getMessage() );
			EE::error( 'Database could not setup.' );
			$mysqli->close();

			return false;
		}
	}

	public static function backupAll() {
		// TODO: Backup all databases.
	}

	/**
	 * Check if database is exist or not.
	 *
	 * @param string $db_name Database name.
	 *
	 * @return bool
	 */
	public static function check_db_exists( $db_name ) {
		$db_exist_query  = "SELECT COUNT(*) AS `exists` FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMATA.SCHEMA_NAME='{$db_name}'";
		$db_exist_exec   = self::execute( $db_exist_query );
		$db_exist_result = $db_exist_exec->fetch_assoc();

		if ( ! empty( $db_exist_result['exists'] ) && $db_exist_result['exists'] > 0 ) {
			return true;
		}

		return false;
	}

}