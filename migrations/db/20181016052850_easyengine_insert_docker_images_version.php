<?php

namespace EE\Migration;

use EE;
use EE\Migration\Base;

class InsertDockerImagesVersion extends Base {

	private static $pdo;

	public function __construct() {

		try {
			self::$pdo = new \PDO( 'sqlite:' . DB );
			self::$pdo->setAttribute( \PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION );
		} catch ( \PDOException $exception ) {
			EE::error( $exception->getMessage() );
		}

	}

	/**
	 * Execute create table query for site and sitemeta table.
	 *
	 * @throws EE\ExitException
	 */
	public function up() {

		EE::log( 'Checking and Pulling required images. This may take some time.' );
		$images = EE\Utils\get_image_versions();

		$query = '';
		foreach ( $images as $image => $tag ) {
			if ( 'easyengine/php5.6' === $image ) {
				continue;
			}
			EE::log( "Checking and Pulling docker image $image:$tag" );
			if ( ! \EE::exec( "docker pull ${image}:${tag}", true, true ) ) {
				throw new \Exception( "Unable to pull ${image}:${tag}. Please check logs for more details." );
			}
			$query .= "INSERT INTO options VALUES( '${image}', '${tag}' );";
		}

		try {
			self::$pdo->exec( $query );
		} catch ( PDOException $exception ) {
			EE::error( 'Encountered Error while inserting in "options" table: ' . $exception->getMessage(), false );
		}
	}

	/**
	 * Execute drop table query for site and sitemeta table.
	 *
	 * @throws EE\ExitException
	 */
	public function down() {

		$query = "DELETE FROM options WHERE key LIKE 'easyengine/%';";

		try {
			self::$pdo->exec( $query );
		} catch ( PDOException $exception ) {
			EE::error( 'Encountered Error while deleting from "options" table: ' . $exception->getMessage(), false );
		}
	}
}
