<?php

use Symfony\Component\Filesystem\Filesystem;
use Cz\Git\GitRepository;

class EE_Git {

	/**
	 * Initializes Directory as repository if not already git repo.
	 * and adds uncommited changes automatically
	 *
	 * @param        $paths
	 * @param string $msg
	 */
	public static function add( $paths, $msg = "Intializating" ) {
		$filesystem = new Filesystem();
		foreach ( $paths as $path ) {
			if ( $filesystem->exists( $path ) ) {
				$git = new GitRepository( $path );
				if ( ! $filesystem->exists( $path . '/.git' ) ) {
					try {
						EE::info( 'EEGit: git init at ' . $path );
						$git->init( $path );
					} catch ( Exception $e ) {
						EE::debug( $e->getMessage() );
						EE::warning( 'Unable to git init at ' . $path );
					}
				}

				if ( $git->hasChanges() ) {
					try {
						EE::debug( 'EEGit: git commit at ' . $path );
						$git->addFile('--all');
						$git->commit( $msg );
					} catch ( Exception $e ) {
						EE::error($e->getMessage());
						EE::error( 'Unable to git commit at ' . $path );
					}
				}
			} else {
				EE::warning( 'EEGit: Path ' . $path . ' not present' );
			}
		}
	}

	public static function checkfilestatus( $repo, $filepath ) {

	}
}