<?php

namespace EE\Model;

use EE;

/**
 * Base EE Model class.
 */
abstract class Base extends \ArrayObject
{

	/**
	 * @var string Table that current model will write to
	 */
	protected static $table;

	/**
	 * @var string Primary key of current model
	 */
	protected static $primary_key;

	/**
	 * @var string It will contain an array of all fields that are present in database in table
	 */
	protected $fields;

	/**
	 * Base constructor.
	 *
	 * @param array $fields Array of fields containing fields from database
	 */
	public function __construct( array $fields = [] ) {
		$this->fields = $fields;
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
		if ( isset( $this->fields[ $name ] ) ) {
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
		$this->fields[ $name ] = $value;
	}

	/**
	 * Overriding isset for correct behaviour while using isset on model objects
	 *
	 * @param string|int $name Name of property to check
	 *
	 * @return bool
	 */
	public function __isset( $name ) {
		return isset( $this->fields[ $name ] );
	}

	/**
	 * Overriding offsetGet for correct behaviour while accessing object properties by array index.
	 *
	 * @param string|int $index Name of property to check
	 *
	 * @throws \Exception
	 *
	 * @return bool
	 */
	public function offsetGet( $index ) {
		return $this->__get( $index );
	}

	/**
	 * Overriding offsetGet for correct behaviour while saving object properties by array index.
	 *
	 * @param string|int $index Name of property to check
	 *
	 * @throws \Exception
	 *
	 * @return bool
	 */
	public function offsetSet( $index, $value ) {
		return $this->__set( $index, $value );
	}

	/**
	 * Overriding offsetGet for correct behaviour while checking array_key_exists.
	 *
	 * @param string|int $index Name of property to check
	 *
	 * @throws \Exception
	 *
	 * @return bool
	 */
	public function offsetExists( $index ) {
		return $this->__isset( $index );
	}

	/**
	 * Returns single model fetched by primary key
	 *
	 * @param string $pk primary key of model
	 *
	 * @throws \Exception
	 *
	 * @return static
	 */
	public static function find( string $pk ) {
		return static::single_array_to_model(
			EE::db()
				->table( static::$table )
				->where( static::$primary_key, $pk )
				->first()
		);
	}

	/**
	 * Throws exception if model is not found
	 *
	 * @param string $pk
	 *
	 * @throws \Exception
	 *
	 * @return static
	 */
	public static function find_or_fail( string $pk ) {
		$model = static::find( $pk );

		if ( ! $model ) {
			throw new \Exception( sprintf( 'Unable to find %s : with primary key: %s and value: %s', __CLASS__, static::$primary_key, $pk ) );
		}
		return $model;
	}

	/**
	 * Exits with error if model is not found
	 *
	 * @param string $pk
	 *
	 * @throws \Exception
	 *
	 * @return static
	 */
	public static function find_or_error( string $pk ) {
		$model = static::find( $pk );

		if ( ! $model ) {
			EE::error( sprintf( 'Unable to find %s : with primary key: %s and value: %s', __CLASS__, static::$primary_key, $pk ) );
		}
		return $model;
	}

	/**
	 * Returns all models.
	 *
	 * @param array $columns Columns to select
	 *
	 * @return array Array of models
	 * @throws \Exception
	 */
	public static function all( $columns = [] ) {
		return static::many_array_to_model(
			EE::db()
				->table( static::$table )
				->select( ...$columns )
				->get()
		);
	}

	/**
	 * Creates new entity in DB
	 *
	 * @param array $columns Columns and values to insert
	 *
	 * @return static
	 * @throws \Exception
	 */
	public static function create( $columns = [] ) {
		$new_model_id = EE::db()->table( static::$table )->insert( $columns );
		return self::find( $new_model_id );
	}

	/**
	 * Saves current model into database
	 *
	 * @throws \Exception
	 *
	 * @return bool Model saved successfully
	 */
	public function save() {
		return EE::db()
			->table( static::$table )
			->where( static::$primary_key, $this[ static::$primary_key ] )
			->update( $this->fields );
	}

	/**
	 * Deletes current model from database
	 *
	 * @throws \Exception
	 *
	 * @return bool Model deleted` successfully
	 */
	public function delete() {
		return EE::db()
			->table( static::$table )
			->where( static::$primary_key, $this->id )
			->delete();
	}

	/**
	 * Updates current model from database
	 *
	 * @throws \Exception
	 *
	 * @return bool Model updated successfully
	 */
	public function update( $columns ) {
		return EE::db()
			->table( static::$table )
			->where( static::$primary_key, $this->id )
			->update( $columns );
	}

	/**
	 * Returns all model with condition
	 *
	 * @param string|array $column Column to search in
	 * @param string|int   $value  Value to match
	 *
	 * @throws \Exception
	 *
	 * @return array
	 */
	public static function where( $column, $value ) {
		return static::many_array_to_model(
			EE::db()
				->table( static::$table )
				->where( $column, $value )
				->all()
		);
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
}
