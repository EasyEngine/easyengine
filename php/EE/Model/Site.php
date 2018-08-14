<?php

namespace EE\Model;

/**
 * Site model class.
 */
class Site extends Base {

	/**
	 * @var string Table of the model from where it will be stored/retrived
	 */
	protected static $table = 'sites';

	/**
	 * @var string Primary/Unique key of the table
	 */
	protected static $primary_key = 'site_url';

	/**
	 * Saves current model into database
	 *
	 * @throws \Exception
	 *
	 * @return bool Model saved successfully
	 */
	public function save() {
		$fields = array_merge( $this->fields, [
			'modified_on' => date( 'Y-m-d H:i:s' ),
		] );

		return EE::db()
			->table( static::$table )
			->where( static::$primary_key, $this[ static::$primary_key ] )
			->update( $fields );
	}


}
