<?php

namespace EE\Model;

use EE\Utils;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

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
				->table( static::$table )
				->where( 'conf_file_path', '=', $config_path )
				->first();

	}

	/**
	 * Get all files in the provided directory.
	 *
	 * @param string $path Path to directory.
	 *
	 * @return array
	 */
	public static function get_files_in_path( $path ) {

		// Allowed file extensions which should be hashed.
		$allowed_ext = [ 'cnf', 'conf', 'ini' ];

		$recursive_iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $path ) );

		$files = array();

		// get all files in the config directory.
		foreach ( $recursive_iterator as $file ) {

			if ( ! $file->isDir() ) {
				$file_path = $file->getPathname();
				$file_info = pathinfo( $file_path );

				// check if a file has an extension.
				$extension = ( ! empty( $file_info['extension'] ) ) ? $file_info['extension'] : '';

				// store files which have valid extensions.
				if ( in_array( $extension, $allowed_ext, true ) ) {
					$files[] = $file_path;
				}
			}
		}

		return $files;
	}

	/**
	 * @param array  $files       Array of files for hash creation.
	 * @param string $config_root Root path where the config is stored.
	 *
	 * @throws \Exception
	 */
	public static function insert_hash_data( $files, $config_root ) {

		// loop through all the files and create it hash record if not exists.
		foreach ( $files as $filepath ) {

			if ( false === ConfigHash::get_config_info( $filepath ) ) {
				Utils\create_config_file_hash( $filepath, $config_root );
			}
		}

	}

}
