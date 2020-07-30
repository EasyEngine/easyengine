<?php

namespace EE\Model;

use EE;

/**
 * Base EE Model class.
 */
abstract class Base {

	/**
	 * @var string Table that current model will write to
	 */
	protected static $table;

	/**
	 * @var string Primary key of current model
	 */
	protected static $primary_key = 'id';

	/**
	 * @var string It will contain an array of all fields that are present in database in table
	 */
	protected $fields;

	/**
	 * @var bool Weather the model needs timestamp whenever it's updated
	 */
	protected static $needs_update_timestamp = false;

	/**
	 * Base constructor.
	 *
	 * @param array $fields Array of fields containing fields from database
	 */
	public function __construct( array $fields = [] ) {
		$this->fields = $fields;
	}

	/**
	 * Throws exception if model is not found
	 *
	 * @param string $value  value to find
	 * @param string $column Column to find in. Defaults to primary key
	 *
	 * @throws \Exception
	 *
	 * @return static
	 */
	public static function find_or_fail( string $value, string $column = null ) {
		$model = static::find( $value, $column );

		if ( ! $model ) {
			throw new \Exception( sprintf( 'Unable to find %s : with primary key: %s and value: %s', __CLASS__, static::$primary_key, $value ) );
		}

		return $model;
	}

	/**
	 * Returns single model fetched by primary key
	 *
	 * @param string $value        value to find
	 * @param array  $columns      Columns to select.
	 *
	 * @throws \Exception
	 *
	 * @return bool|static
	 */
	public static function find( string $value, array $columns = [] ) {

		$model = EE::db()
			->table( static::$table )
			->select( ...$columns )
			->where( static::$primary_key, $value )
			->first();

		if ( false === $model ) {
			return false;
		}

		return static::single_array_to_model( $model );
	}

	/**
	 * Converts single row fetched from database into model
	 *
	 * @param array $arr Associative array representing a row from database
	 *
	 * @return mixed
	 */
	protected static function single_array_to_model( array $arr ) {
		return new static( $arr );
	}

	/**
	 * Exits with error if model is not found
	 *
	 * @param string $value  value to find
	 * @param string $column Column to find in. Defaults to primary key
	 *
	 * @throws \Exception
	 *
	 * @return static
	 */
	public static function find_or_error( string $value, string $column = null ) {
		$model = static::find( $value, $column );

		if ( ! $model ) {
			EE::error( sprintf( 'Unable to find %s : with primary key: %s and value: %s', __CLASS__, static::$primary_key, $value ) );
		}

		return $model;
	}

	/**
	 * Returns all models.
	 *
	 * @param array $columns Columns to select
	 *
	 * @throws \Exception
	 *
	 * @return array Array of models
	 */
	public static function all( $columns = [] ) {
		$models = EE::db()
			->table( static::$table )
			->select( ...$columns )
			->get();

		return static::many_array_to_model( $models );
	}

	/**
	 * Converts an array of result from database to models
	 *
	 * @param array $arr Array of results from database
	 *
	 * @return array
	 */
	protected static function many_array_to_model( array $arr ) {
		$result = [];

		foreach ( $arr as $model ) {
			$result[] = static::single_array_to_model( $model );
		}

		return $result;
	}

	/**
	 * Creates new entity in DB
	 *
	 * @param array $columns Columns and values to insert
	 *
	 * @throws \Exception
	 *
	 * @return bool Model created successfully
	 */
	public static function create( $columns = [] ) {
		return EE::db()->table( static::$table )->insert( $columns );
	}

	/**
	 * Returns all model with condition
	 *
	 * @param string|array $column Column to search in
	 * @param string $value        Value to match
	 *
	 * @throws \Exception
	 *
	 * @return array
	 */
	public static function where( $column, $value = '' ) {

		if ( is_bool( $value ) ) {
			$value = (int) $value;
		}

		return static::many_array_to_model(
			EE::db()
				->table( static::$table )
				->where( $column, $value )
				->all()
		);
	}

	/**
	 * In model, every value is get in fields array.
	 * We populate it either during constructor or during find() method call
	 *
	 * This gives us benefit that models do not have to define properties in class
	 * They are automatically defined when fetched from database!
	 *
	 * @param string $name Name of property to get
	 *
	 * @throws \Exception
	 *
	 * @return mixed Value of property
	 */
	public function __get( string $name ) {
		if ( array_key_exists( $name, $this->fields ) ) {
			return $this->fields[ $name ];
		}

		throw new \Exception( "Unable to find variable: $name" );
	}

	/**
	 * In model, every value is set in fields array.
	 *
	 * This gives us benefit that models do not have to define the logic of saving them in database.
	 * While saving models, we use the $fields array to save it in database
	 *
	 *
	 * @param string $name  Name of property to set
	 * @param mixed  $value Value of property to set
	 */
	public function __set( string $name, $value ) {
		$this->fields[$name] = $value;
	}

	/**
	 * Overriding isset for correct behaviour while using isset on model objects
	 *
	 * @param string|int $name Name of property to check
	 *
	 * @return bool
	 */
	public function __isset( $name ) {
		return isset( $this->fields[$name] );
	}

	/**
	 * Removes a property from model
	 * It's done by removing it from $fields array
	 *
	 * @param string $name Name of property to unset
	 */
	public function __unset( $name ) {
		unset( $this->fields[$name] );
	}

	/**
	 * Saves current model into database
	 *
	 * @throws \Exception
	 *
	 * @return bool Model saved successfully
	 */
	public function save() {

		$fields = $this->fields;

		foreach ( $fields as $key => $value ) {
			if ( is_bool( $value ) ) {
				$fields[ $key ] = (int) $value;
			}
		}

		if ( static::$needs_update_timestamp ) {
			$fields = array_merge(
				$fields, [
					'modified_on' => date( 'Y-m-d H:i:s' ),
				]
			);
		}

		$primary_key_column = static::$primary_key;

		return EE::db()
			->table( static::$table )
			->where( $primary_key_column, $this->$primary_key_column )
			->update( $fields );
	}

	/**
	 * Deletes current model from database
	 *
	 * @throws \Exception
	 *
	 * @return bool Model deleted successfully
	 */
	public function delete() {
		$primary_key_column = static::$primary_key;

		return EE::db()
			->table( static::$table )
			->where( $primary_key_column, $this->$primary_key_column )
			->delete();
	}

	/**
	 * Updates current model from database
	 *
	 * @param array $where          Where conditions to update
	 * @param array $updated_values Updated values
	 *
	 * @throws \Exception
	 *
	 * @return bool Model updated successfully
	 */
	public static function update( array $where, array $updated_values ) {

		return EE::db()
			->table( static::$table )
			->where( $where )
			->update( $updated_values );
	}
}
