<?php

namespace EE\Migration;

use EE;
use EE\Migration\Base;
use Symfony\Component\Process\Process;

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
		$skip_download = [
			'easyengine/php5.6',
			'easyengine/php7.0',
			'easyengine/php7.2',
			'easyengine/php7.3',
			'easyengine/php7.4',
			'easyengine/php',
			'easyengine/php8.0',
			'easyengine/php8.1',
			'easyengine/mailhog',
			'easyengine/newrelic-daemon',
		];

		$pull_queue = [];
		foreach ( $images as $image => $tag ) {
			if ( in_array( $image, $skip_download ) ) {
				continue;
			}
			$pull_queue[] = [ $image, $tag ];
		}

		$concurrency = 4;
		$running = [];
		$successful_pulls = [];

		while ( ! empty( $pull_queue ) || ! empty( $running ) ) {
			// Start new processes if under concurrency limit
			while ( count( $running ) < $concurrency && ! empty( $pull_queue ) ) {
				list( $image, $tag ) = array_shift( $pull_queue );
				EE::log( "Checking and Pulling docker image $image:$tag" );
				$process = new Process( [ 'docker', 'pull', "$image:$tag" ] );
				$process->start();
				$running[] = [ $process, $image, $tag ];
			}

			// Check for finished processes
			foreach ( $running as $key => list( $process, $image, $tag ) ) {
				if ( ! $process->isRunning() ) {
					if ( ! $process->isSuccessful() ) {
						throw new \Exception( "Unable to pull $image:$tag: " . $process->getErrorOutput() );
					}
					$successful_pulls[] = [ $image, $tag ];
					unset( $running[ $key ] );
				}
			}

			usleep( 100000 ); // Sleep 100ms to avoid busy loop
		}

		foreach ( $successful_pulls as list( $image, $tag ) ) {
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
