<?php

class EE_DB {

	/**
	 * @var PDO Instance of PDO class
	 */
	private static $pdo;
	private $tables;
	private $select;
	private $where;
	private $limit;
	private $offset;

	public function __construct() {
		if ( empty( self::$pdo ) ) {
			self::init_db();
		}

		$this->select = '';
		$this->tables = '';
		$this->limit  = '';
		$this->offset = '';
		$this->where  = [
			'query_string' => null,
			'bindings'      => null,
		];
	}

	/**
	 * Function to initialize db and db connection.
	 */
	private static function init_db() {
		try {
			self::$pdo = new PDO( 'sqlite:' . DB );
		} catch ( PDOException $exception ) {
			EE::error( $exception->getMessage() );
		}

		if ( ! ( file_exists( DB ) ) ) {
			self::create_required_tables();
		}
	}

	/**
	 * Sqlite database creation.
	 */
	private static function create_required_tables() {
		$query = 'CREATE TABLE sites (
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
			is_ssl_wildcard BOOLEAN DEFAULT 0,
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

		try {
			self::$pdo->exec( $query );
		} catch ( PDOException $exception ) {
			EE::error( 'Encountered Error while creating table: ' . $exception->getMessage() );
		}
	}

	/**
	 * Check if a site entry exists in the database.
	 *
	 * @param string $site_name Name of the site to be checked.
	 *
	 * @throws Exception
	 *
	 * @return bool Success.
	 */
	public static function site_in_db( $site_name ) {
		$db = new EE_DB();
		$site = $db->table( 'sites' )
			->select( 'id' )
			->where( 'sitename', $site_name )
			->first();

		if ( $site ) {
			return true;
		}

		return false;
	}

	/**
	 * Fetches first record from current query
	 *
	 * @return array Record
	 * @throws Exception
	 */
	public function first() {
		$pdo_statement = $this->common_retrieval_function();

		if ( ! $pdo_statement ) {
			return false;
		}

		return $pdo_statement->fetch();
	}

	/**
	 * Common retrival function that runs current 'select' query.
	 * Other methods (like get and first) can use this to provide higher level functionality on top of it
	 *
	 * @return bool|PDOStatement
	 * @throws Exception
	 */
	private function common_retrieval_function() {
		if ( null === $this->tables ) {
			throw new Exception( 'Select: No table specified' );
		}

		$where = $this->where['query_string'];
		$this->select === '' ? $this->select = '*' : '';

		$query = "SELECT $this->select FROM $this->tables{$where}{$this->limit}{$this->offset};";

		$pdo_statement = self::$pdo->prepare( $query );
		$pdo_statement->setFetchMode( PDO::FETCH_ASSOC );

		$bindings = $this->where['bindings'] ?? [];

		foreach ( $bindings as $key => $binding ) {
			$pdo_statement->bindValue( $key + 1, $binding );
		}

		$result = $pdo_statement->execute();

		if ( ! $result ) {
			EE::debug( self::$pdo->errorInfo() );

			return false;
		}

		return $pdo_statement;
	}

	/**
	 * Adds where condition in query.
	 *
	 * i.e. where('id', 100) or where('id', '>', 100)
	 *   or where([
	 *         [ 'id', '<', 100 ],
	 *         [ 'name', 'ee' ]
	 *      ])
	 *
	 * Supported operators are: '=', '<', '>', '<=', '>=', '==', '!=', '<>', 'like', 'in'
	 *
	 * @param ...$args One or more where condition.
	 *
	 * @throws Exception
	 * @return EE_DB
	 */
	public function where( ...$args ) {
		$args       = func_get_args();
		$conditions = [];

		if ( 'array' === gettype( $args[0] ) ) {
			foreach ( $args[0] as $condition ) {
				$conditions[] = $this->get_where_fragment( $condition );
			}
		} else {
			$conditions[] = $this->get_where_fragment( $args );
		}

		$this->where = [
			'query_string' => ' WHERE ' . implode( ' AND ', array_column( $conditions, 'query_string' ) ),
			'bindings'     => array_column( $conditions, 'binding' ),
		];

		return $this;
	}

	/**
	 * Select data from the database.
	 *
	 * @param array $args Columns to select
	 *
	 * @throws Exception If no tables are specified
	 *
	 * @return EE_DB
	 */
	public function select( ...$args ) {

		if ( empty( $args ) ) {
			$columns = '*';
		} else {
			$columns = implode( ', ', $args );
		}

		$this->select = $columns;

		return $this;
	}

	/**
	 * Selects table to do operation on.
	 *
	 * @param ...$args Tables to run query on.
	 *
	 * @return EE_DB
	 */
	public function table( ...$args ) {
		$this->tables = implode( ', ', $args );

		return $this;
	}

	/**
	 * Check if a site entry exists in the database as well as if it is enbaled.
	 *
	 * @param String $site_name Name of the site to be checked.
	 *
	 * @throws Exception
	 *
	 * @return bool true  if site is enabled,
	 *              false if disabled or site does not exists.
	 */
	public static function site_enabled( $site_name ) {

		$db = new EE_DB();
		$site = $db->table( 'sites' )
			->select( 'id', 'is_enabled' )
			->where( 'sitename', $site_name )
			->first();

		if ( $site ) {
			return $site['is_enabled'];
		}

		return false;
	}

	/**
	 * Get site type.
	 *
	 * @param String $site_name Name of the site.
	 *
	 * @throws Exception
	 *
	 * @return string type of site.
	 */
	public static function get_site_command( $site_name ) {
		$db = new EE_DB();
		$site = $db->table( 'sites' )
			->select( 'site_command' )
			->where( 'sitename', $site_name )
			->first();

		return $site['site_command'];
	}

	/**
	 * Fetches all records from current query
	 *
	 * @return array All records
	 * @throws Exception
	 */
	public function get() {
		$pdo_statement = $this->common_retrieval_function();

		if ( ! $pdo_statement ) {
			return false;
		}

		return $pdo_statement->fetchAll();
	}

	/**
	 * Returns all migrations from table.
	 */
	public static function get_migrations() {

		$db = new EE_DB();
		$migrations = $db->table( 'migrations' )
			->select( 'migration' )
			->get();

		return array_column( $migrations, 'migration' );
	}

	/**
	 * Adds limit to query.
	 *
	 * @param int $limit Limit value.
	 *
	 * @return EE_DB
	 */
	public function limit( int $limit ) {
		$this->limit = ' LIMIT ' . (string) $limit;

		return $this;
	}

	/**
	 * Adds offset to query.
	 *
	 * @param int $offset Offset of query
	 *
	 * @return EE_DB
	 */
	public function offset( int $offset ) {
		$this->offset = ' OFFSET ' . (string) $offset;

		return $this;
	}

	/**
	 * Insert row in table.
	 *
	 * @param array $data in key value pair.
	 *
	 * @throws Exception If no table or more than one tables are specified
	 *
	 * @return bool
	 */
	public function insert( $data ) {

		$fields = implode( ', ', array_keys( $data ) );
		$values = implode( array_fill( 0, count( $data ), '?' ) );

		if ( empty( $this->tables ) ) {
			throw new Exception( 'Insert: No table specified' );
		}

		if ( strpos( $this->tables, ',' ) !== false ) {
			throw new Exception( 'Insert: Multiple table specified' );
		}

		$query = "INSERT INTO $this->tables ($fields) VALUES ($values);";

		$pdo_statement = self::$pdo->prepare( $query );
		$bindings      = array_values( $data );

		foreach ( $bindings as $key => $value ) {
			$pdo_statement->bindValue( $key + 1, $value );
		}

		if ( ! $result ) {
			EE::debug( self::$pdo->errorInfo() );

			return false;
		}

		return true;
	}

	/**
	 * Update row in table.
	 *
	 * @param array $values Associative array of columns and their values
	 *
	 * @throws Exception If no table are specified or multiple table are specified or no where clause are specified
	 *
	 * @return bool
	 */
	public function update( $values ) {
		if ( empty( $this->tables ) ) {
			throw new Exception( 'Update: No table specified' );
		}

		if ( empty( $this->where ) ) {
			throw new Exception( 'Update: No where clause specified' );
		}

		if ( strpos( $this->tables, ',' ) !== false ) {
			throw new Exception( 'Update: Multiple table specified' );
		}

		if ( empty( $values ) ) {
			return false;
		}

		$set_keys     = array_keys( $values );
		$set_bindings   = array_values( $values );
		$where_bindings = $this->where['bindings'];

		$set_clause = implode( $set_keys, ' = ?, ' ) . ' = ?';

		$query         = "UPDATE $this->tables SET $set_clause{$this->where['query_string']}";
		$pdo_statement = self::$pdo->query( $query );

		$counter = 0;  //We need counter here as we need to bind values of both  SET and WHERE clauses

		foreach ( $set_bindings as $binding ) {
			$pdo_statement->bindValue( ++ $counter, $binding );
		}

		foreach ( $where_bindings as $binding ) {
			$pdo_statement->bindValue( ++ $counter, $binding );
		}

		$result = $pdo_statement->execute();

		if ( ! $result ) {
			EE::debug( self::$pdo->errorInfo() );

			return false;
		}

		return true;
	}

	/**
	 * Delete data from table.
	 *
	 * @throws Exception If no table are specified or multiple table are specified or no where clause are specified
	 *
	 * @return bool Success.
	 */
	public function delete() {
		if ( empty( $this->tables ) ) {
			throw new Exception( 'Delete: No table specified' );
		}

		if ( empty( $this->where ) ) {
			throw new Exception( 'Delete: No where clause specified' );
		}

		if ( strpos( $this->tables, ',' ) !== false ) {
			throw new Exception( 'Delete: Multiple table specified' );
		}

		$query = "DELETE FROM $this->tables{$this->where['query_string']}";

		$pdo_statement = self::$pdo->query( $query );

		foreach ( $this->where['bindings'] as $key => $binding ) {
			$pdo_statement->bindValue( $key + 1, $binding );
		}

		$result = $pdo_statement->execute();

		if ( ! $result ) {
			EE::debug( self::$pdo->errorInfo() );

			return false;
		}

		return true;
	}

	/**
	 * Returns a query fragment for where clause
	 *
	 * If the param given is ['column', 100], it returns ['column = ?', 100]
	 * If the param given is ['column', '>', 100], it returns ['column > ?', 100]
	 *
	 * @param array $condition An array of format [column, operator, value] or [column, value]
	 *
	 * @throws Exception
	 *
	 * @return array prepared query string and its corresponding binding
	 */
	private function get_where_fragment( $condition ) {
		$column   = $condition[0];
		$operator = '=';

		if ( 'string' !== gettype( $column ) ) {
			throw new Exception( 'Where clause column must be of type string' );
		}

		if ( isset( $condition[2] ) ) {
			$operator          = $condition[1];
			$allowed_operators = [ '=', '<', '>', '<=', '>=', '==', '!=', '<>', 'like', 'in' ];

			if ( ! in_array( strtolower( $operator ), $allowed_operators ) ) {
				throw new Exception( 'Where clause operator should be in one of following: ' . implode( ' ', $allowed_operators ) );
			}

			$value = $condition[2];
		} elseif ( isset( $condition[1] ) ) {
			$value = $condition[1];
		} else {
			throw new Exception( 'Where clause value must be set' );
		}

		if ( 'string' !== gettype( $operator ) || ! in_array( gettype( $value ), [ 'string', 'integer', 'boolean' ], true ) ) {
			throw new Exception( 'Where clause operator and value must be string' );
		}

		return [
			'query_string' => "$column $operator ?",
			'binding'      => $value,
		];
	}
}
