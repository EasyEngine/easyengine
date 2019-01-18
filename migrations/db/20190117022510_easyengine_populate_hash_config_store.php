<?php
namespace EE\Migration;

use EE;
use EE\Model\Site;
use EE\Model\ConfigHash;
use EE\Utils;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class PopulateHashConfigStore extends Base {

	private static $pdo;

	public function __construct() {

		parent::__construct();

		$this->sites = Site::all();

		if ( $this->is_first_execution ) {
			$this->skip_this_migration = true;
		}

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

		if ( $this->skip_this_migration ) {
			EE::debug( 'Skipping hash data population for configs.' );
			return;
		}

		// Loop through each site and store configuration hash.
		foreach ( $this->sites as $site ) {

			// site config paths.
			$site_conf_paths = [
				$site->site_fs_path . DIRECTORY_SEPARATOR . 'config',
				$site->site_fs_path . DIRECTORY_SEPARATOR . 'services',
			];

			foreach ( $site_conf_paths as $site_conf_path ) {

				// get all files in given path.
				$files = $this->get_files_in_path( $site_conf_path );

				// insert hash record for found files.
				$this->insert_hash_data( $files, $site->site_url );
			}
		}

		$services = [ 'nginx-proxy', 'mariadb', 'redis' ];

		//Loop through global services and store configuration hash.
		foreach ( $services as $service ) {

			$service_path = EE_ROOT_DIR . DIRECTORY_SEPARATOR . 'services' . DIRECTORY_SEPARATOR . $service;

			// get all files in given path.
			$files = $this->get_files_in_path( $service_path );

			// insert hash record for found files.
			$this->insert_hash_data( $files, $service );
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

	/**
	 * Get all files in the provided directory.
	 *
	 * @param string $path Path to directory.
	 *
	 * @return array
	 */
	private function get_files_in_path( $path ) {

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
	private function insert_hash_data( $files, $config_root ) {

		// loop through all the files and create it hash record if not exists.
		foreach ( $files as $filepath ) {

			if ( false === ConfigHash::get_config_info( $filepath ) ) {
				Utils\create_config_file_hash( $filepath, $config_root );
			}
		}

	}
}
