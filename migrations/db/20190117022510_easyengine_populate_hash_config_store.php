<?php
namespace EE\Migration;

use EE;
use EE\Model\Site;
use EE\Model\ConfigHash;

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
				$files = ConfigHash::get_files_in_path( $site_conf_path );

				// insert hash record for found files.
				ConfigHash::insert_hash_data( $files, $site->site_url );
			}
		}

		$services = [ 'nginx-proxy', 'mariadb', 'redis' ];

		//Loop through global services and store configuration hash.
		foreach ( $services as $service ) {

			$service_path = EE_ROOT_DIR . DIRECTORY_SEPARATOR . 'services' . DIRECTORY_SEPARATOR . $service;

			// get all files in given path.
			$files = ConfigHash::get_files_in_path( $service_path );

			// insert hash record for found files.
			ConfigHash::insert_hash_data( $files, $service );
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
