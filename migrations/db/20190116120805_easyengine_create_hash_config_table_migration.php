<?php
namespace EE\Migration;

use EE;

class CreateHashConfigTableMigration extends Base {

	private static $pdo;

	public function __construct() {

		try {
			self::$pdo = new \PDO( 'sqlite:' . DB );
			self::$pdo->setAttribute( \PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION );
		} catch ( \PDOException $exception ) {
			EE::error( $exception->getMessage() );
		}

	}

	/**
	 * Execute create table query for hash_config_store table.
	 *
	 * @throws EE\ExitException
	 */
	public function up() {

		$query = 'CREATE TABLE hash_config_store (
			id INTEGER,
			conf_file_name VARCHAR NOT NULL,
			conf_file_path VARCHAR NOT NULL,
			conf_file_hash VARCHAR NOT NULL,
			config_root VARCHAR NOT NULL,
			ee_version VARCHAR NOT NULL,
			PRIMARY KEY (id)
		);';

		try {
			EE::debug( 'Executing hash config table creation query!' );
			self::$pdo->exec( $query );
		} catch ( PDOException $exception ) {
			EE::error( 'Encountered Error while creating table: ' . $exception->getMessage(), false );
		}
	}

	/**
	 * Execute drop table query for hash_config_store table.
	 *
	 * @throws EE\ExitException
	 */
	public function down() {

		$query = 'DROP TABLE hash_config_store';

		try {
			self::$pdo->exec( $query );
		} catch ( PDOException $exception ) {
			EE::error( 'Encountered Error while dropping table: ' . $exception->getMessage(), false );
		}
	}
}
