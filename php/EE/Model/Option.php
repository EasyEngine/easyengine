<?php

namespace EE\Model;

/**
 * Option model class.
 */
class Option extends Base {

	protected static $table = 'options';

	protected static $primary_key = 'key';

	/**
	 * Sets a key on options table
	 *
	 * @param string $key   Key of option
	 * @param string $value Value of option
	 *
	 * @throws \Exception
	 * @return bool Key set or not
	 */
	public static function set( string $key, string $value ) {
		$existing_key = static::find( $key );

		if ( empty( $existing_key ) ) {
			return static::create(
				[
					'key'   => $key,
					'value' => $value,
				]
			);
		}

		$existing_key->value = $value;

		return $existing_key->save();
	}

	/**
	 * Gets a key from options table
	 *
	 * @param string $key Key of option
	 *
	 * @throws \Exception
	 * @return bool|Option
	 */
	public static function get( string $key ) {
		$option = static::find( $key );

		return false === $option ? false : $option->value;
	}
}
