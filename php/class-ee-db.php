<?php

class EE_DB {

	private static $db;
	private $tables;
	private $where;
	private $limit;

	public function __construct() {
		if ( empty( self::$db ) ) {
			self::init_db();
		}
	}

	public function __destruct() {
		self::$db->close();
	}

	/**
	 * Function to initialize db and db connection.
	 */
	private static function init_db() {
		if ( ! ( file_exists( DB ) ) ) {
			self::$db = self::create_required_tables();
		} else {
			self::$db = new SQLite3( DB );
			if ( ! self::$db ) {
				EE::error( self::$db->lastErrorMsg() );
			}
		}
	}

	/**
	 * Sqlite database creation.
	 */
	private static function create_required_tables() {
		self::$db = new SQLite3( DB );
		$query    = 'CREATE TABLE sites (
			id INTEGER NOT NULL,
			sitename VARCHAR,
			site_type VARCHAR,
			site_title VARCHAR,
			site_command VARCHAR,
			proxy_type VARCHAR,
			cache_type VARCHAR,
			site_path VARCHAR,
			created_on DATETIME,
			is_enabled BOOLEAN DEFAULT 1,
			is_ssl BOOLEAN DEFAULT 0,
			storage_fs VARCHAR,
			storage_db VARCHAR,
			db_name VARCHAR,
			db_user VARCHAR,
			db_password VARCHAR,
			db_root_password VARCHAR,
			db_host VARCHAR,
			db_port VARCHAR,
			wp_user VARCHAR,
			wp_pass VARCHAR,
			email VARCHAR,
			php_version VARCHAR,
			PRIMARY KEY (id),
			UNIQUE (sitename),
			CHECK (is_enabled IN (0, 1)),
			CHECK (is_ssl IN (0, 1))
		);';

		$query .= 'CREATE TABLE migrations (
			migration VARCHAR,
			timestamp DATETIME
		);';

		$query .= 'CREATE TABLE services (
			id INTEGER NOT NULL,
			sitename VARCHAR,
			phpmyadmin BOOLEAN DEFAULT 0,
			mailhog BOOLEAN DEFAULT 0,
			postfix BOOLEAN DEFAULT 0,
			phpredisadmin BOOLEAN DEFAULT 0,
			adminer BOOLEAN DEFAULT 0,
			anemometer BOOLEAN DEFAULT 0,
			debug BOOLEAN DEFAULT 0,
			PRIMARY KEY (id),
			FOREIGN KEY (id) REFERENCES sites(id)
		);';

		$query .= 'CREATE TABLE cron (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			sitename VARCHAR,
			command VARCHAR,
			schedule VARCHAR
		);';

		self::$db->exec( $query );
	}

	/**
	 * Select table to do operation on.
	 *
	 * @param array $data in key value pair.
	 *
	 * @return bool
	 */
	public function table() {
		$this->tables = func_get_args();
		return $this;
	}

	/**
	 * Insert row in table.
	 *
	 * @param array $data in key value pair.
	 *
	 * @return bool
	 */
	public function where() {
		$this->where = func_get_args();
		return $this;
	}

	/**
	 * Insert row in table.
	 *
	 * @param array $data in key value pair.
	 *
	 * @return bool
	 */
	public function limit( $limit ) {
		$this->limit = $limit;
		return $this;
	}

	/**
	 * Insert row in table.
	 *
	 * @param array $data in key value pair.
	 *
	 * @return bool
	 */
	public function insert( $data ) {

		$fields  = implode( ', ', array_keys( $data ) );
		$values = '"' . implode( '", "', $data ) . '"';

		if ( empty( $this->table ) ) {
			throw new Exception( 'Insert: No table specified' );
		}

		if( count( $this->table ) > 1) {
			throw new Exception( 'Insert: Multiple table specified' );
		}
		$table = $this->tables[0];

		$query = "INSERT INTO `$table` ($fields) VALUES ($values);";

		$query_exec = self::$db->exec( $query );

		if ( ! $query_exec ) {
			EE::debug( self::$db->lastErrorMsg() );
			return false;
		}

		return true;
	}

	/**
	 * Select data from the database.
	 *
	 * @return array
	 */
	public function select( ...$args ) {

		if ( null === $this->tables ) {
			throw new Exception( 'Select: No table specified' );
		}

		$tables = implode( ', ', $this->tables );

		if ( empty( $args ) ) {
			$columns = '*';
		} else {
			$columns = implode( ', ', $args );
		}

		$query = "SELECT $columns FROM $tables" ;

		if ( null !== $this->where ) {
			$conditions = implode( ' AND ', $this->where );
			$query .= " WHERE $conditions";
		}
		if ( null !== $this->limit ) {
			$query .= ' LIMIT ' . $this->limit;
		}

		$query_exec = self::$db->query( $query );
		$result     = array();

		if ( $query_exec ) {
			while ( $row = $query_exec->fetchArray( SQLITE3_ASSOC ) ) {
				$result[] = $row;
			}
		}
		return $result;
	}


	/**
	 * Update row in table.
	 *
	 * @param        $data
	 * @param        $where
	 *
	 * @return bool
	 */
	public function update( ...$values ) {
		if ( empty( $this->tables ) ) {
			throw new Exception( 'Update: No table specified' );
		}

		if ( empty( $this->where ) ) {
			throw new Exception( 'Delete: No where clause specified' );
		}

		if( count( $this->tables ) > 1) {
			throw new Exception( 'Update: Multiple table specified' );
		}
		$table = $this->tables[0];

		$values     = implode( ', ', $values );
		$conditions = implode( ' AND ', $this->where );

		if ( empty( $values ) ) {
			return false;
		}

		$table = $this->tables[0];
		$query      = "UPDATE `$table` SET $values WHERE $conditions";
		$query_exec = self::$db->exec( $query );
		if ( ! $query_exec ) {
			EE::debug( self::$db->lastErrorMsg() );
			return false;
		}
		return true;
	}

	/**
	 * Delete data from table.
	 *
	 * @param        $where
	 *
	 * @return bool
	 */
	public function delete() {
		if ( empty( $this->tables ) ) {
			throw new Exception( 'Delete: No table specified' );
		}

		if ( empty( $this->where ) ) {
			throw new Exception( 'Delete: No where clause specified' );
		}

		if( count( $this->tables ) > 1) {
			throw new Exception( 'Delete: Multiple table specified' );
		}

		$table = $this->tables[0];

		$conditions   = implode( ' AND ', $this->where );
		$query = "DELETE FROM `$table` WHERE $conditions";

		$query_exec = self::$db->exec( $query );

		if ( ! $query_exec ) {
			EE::debug( self::$db->lastErrorMsg() );
		} else {
			return true;
		}

		return false;
	}

	/**
	 * Check if a site entry exists in the database.
	 *
	 * @param String $site_name Name of the site to be checked.
	 *
	 * @return bool Success.
	 */
	public static function site_in_db( $site_name ) {

		$site = self::select(
			array( 'id' ), array(
				'sitename' => $site_name,
			)
		);

		if ( $site ) {
			return true;
		}
		return false;
	}

	/**
	 * Check if a site entry exists in the database as well as if it is enbaled.
	 *
	 * @param String $site_name Name of the site to be checked.
	 *
	 * @return bool true  if site is enabled,
	 *              false if disabled or site does not exists.
	 */
	public static function site_enabled( $site_name ) {

		$site = self::select(
			array( 'id', 'is_enabled' ), array(
				'sitename' => $site_name,
			)
		);

		if ( 1 === count( $site ) ) {
			return $site[0]['is_enabled'];
		}

		return false;
	}

	/**
	 * Get site type.
	 *
	 * @param String $site_name Name of the site.
	 *
	 * @return string type of site.
	 */
	public static function get_site_command( $site_name ) {

		if ( empty ( self::$db ) ) {
			self::init_db();
		}

		$site = self::select( [ 'site_type' ], [ 'sitename' => $site_name ], 'sites', 1 );

		return $site['site_type'];
	}

	/**
	 * Returns all migrations from table.
	 */
	public static function get_migrations() {

		$sites = self::select( [ 'migration' ], [], 'migrations' );
		if ( empty( $sites ) ) {
			return [];
		}

		return array_column( $sites, 'migration' );
	}
}
