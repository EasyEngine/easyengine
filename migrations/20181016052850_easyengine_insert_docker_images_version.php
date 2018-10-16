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

		EE::log( 'Checking/Pulling required images' );
		$images = [
			'easyengine/cron'        => 'v4.0.0-beta.5',
			'easyengine/mailhog'     => 'v4.0.0-beta.5',
			'easyengine/mariadb'     => 'v4.0.0-beta.5',
			'easyengine/nginx-proxy' => 'v4.0.0-beta.6',
			'easyengine/nginx'       => 'v4.0.0-beta.5',
			'easyengine/php'         => 'v4.0.0-beta.6',
			'easyengine/phpmyadmin'  => 'v4.0.0-beta.5',
			'easyengine/postfix'     => 'v4.0.0-beta.5',
			'easyengine/redis'       => 'v4.0.0-beta.5',
		];

		$query = '';
		foreach ( $images as $image => $tag ) {
			EE::debug( "Checking/Pulling docker image $image:$tag" );
			if ( ! \EE::exec( "docker pull ${image}:${tag}" ) ) {
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
