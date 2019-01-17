<?php

namespace EE\Model;

/**
 * ConfigHash model class.
 */
class ConfigHash extends Base {

	protected static $table = 'hash_config_store';

	/**
	 * Return hash of the config path provided.
	 *
	 * @param string $config_path Path to config file.
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	public static function get_config_hash( $config_path ) {

		$config_data = self::get_config_info( $config_path );

		if ( ! empty( $config_data ) ) {
			return $config_data['conf_file_hash'];
		}

	}

	/**
	 * Get single record of the given config path in the config root.
	 *
	 * @param string $config_path Path to config file.
	 *
	 * @return array
	 * @throws \Exception
	 */
	public static function get_config_info( $config_path ) {

		return \EE::db()
				->table( 'hash_config_store' )
				->where( 'conf_file_path', '=', $config_path )
				->first();

	}

}
