<?php

use Symfony\Component\Filesystem\Filesystem;
use NoiseLabs\ToolKit\ConfigParser\ConfigParser;

class EE_MySql {

	/**
	 * Makes connection with MySQL server.
	 */
	public static function connect() {
		$fileSystem  = new Filesystem();
		$configParse = new ConfigParser();
		try {
			if ( $fileSystem->exists( '/etc/mysql/conf.d/my.cnf' ) ) {
				$my_cnf_file = '/etc/mysql/conf.d/my.cnf';
			} else if ( $fileSystem->exists( '~/.my.cnf' ) ) {
				$my_cnf_file = '~/.my.cnf';
			} else {
				return false;
			}
			$configParse->read( $my_cnf_file );
			$mysql_db_credentials = ! empty( $configParse['client'] ) ? $configParse['client'] : '';
			$db_root_username     = ! empty( $mysql_db_credentials['user'] ) ? $mysql_db_credentials['user'] : '';
			$db_root_password     = ! empty( $mysql_db_credentials['password'] ) ? $mysql_db_credentials['password'] : '';
			$mysqli               = new mysqli( 'localhost', $db_root_username, $db_root_password );
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
	 * @param      $statement
	 * @param      $err_msg
	 * @param bool $log
	 *
	 * @return bool|mysqli
	 */
	public static function execute( $statement, $err_msg = '', $log = true ) {
		$mysqli = self::connect();
		if ( $log ) {
			EE::log( 'Executing MySQL Statement : ' . $statement );
		}
		try {
			$exec_query = $mysqli->query( $statement );
			if ( ! $exec_query && ! empty( $err_msg ) ) {
				EE::error( $err_msg );
			}
		} catch ( Exception $e ) {
			EE::debug( $e->getMessage() );
			EE::error( 'Database could not setup.' );
		} finally {
			$mysqli->close();
		}
	}

	public static function backupAll() {
		// TODO: Backup all databases.
	}
}