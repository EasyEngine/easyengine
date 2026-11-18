<?php

namespace EE;

use EE;

/**
 * Serialises migrations across ee processes with flock(), which the kernel drops when the holder exits or crashes.
 *
 * Commands that may migrate take it shared to read the DB version, so they wait while a migration runs, and exclusive to migrate.
 */
class MigrationLock {

	/** Seconds to wait for another process before giving up; `EE_MIGRATION_LOCK_TIMEOUT` overrides it. */
	const DEFAULT_TIMEOUT = 3600;

	/** Seconds of waiting before telling the user, so brief version checks by other processes stay silent. */
	const NOTICE_AFTER = 2;

	/** Exit code (sysexits EX_TEMPFAIL) when a command didn't run and should be retried: it gave up waiting, or ee was updated while it waited. `ee cli update` checks the new phar for it, so never change it. */
	const RETRY_EXIT_CODE = 75;

	/** @var string */
	private $path;

	/** @var int */
	private $timeout;

	/** @var resource|null */
	private $handle = null;

	/** @var bool Whether the lock could not be opened or isn't supported, so callers run unlocked. */
	private $unavailable = false;

	/**
	 * @param string|null $path    Lock file, null for the one all ee processes share. Never unlink it: a new inode would let a second process lock it too.
	 * @param int|null    $timeout Seconds to wait, 0 to fail at once, null for the default.
	 */
	public function __construct( $path = null, $timeout = null ) {
		$this->path = null === $path ? EE_ROOT_DIR . '/db/migration.lock' : $path;
		if ( null === $timeout ) {
			$env     = getenv( 'EE_MIGRATION_LOCK_TIMEOUT' );
			$timeout = ( false !== $env && is_numeric( $env ) ) ? (int) $env : self::DEFAULT_TIMEOUT;
		}
		$this->timeout = max( 0, (int) $timeout );
	}

	/**
	 * Takes the lock, waiting up to the timeout for other processes. Errors out with RETRY_EXIT_CODE when the wait times out.
	 *
	 * @param bool $exclusive True to migrate, false to only wait for a running migration.
	 *
	 * @return bool Whether this process had to wait.
	 */
	public function acquire( $exclusive ) {
		if ( ! $this->open() ) {
			return false;
		}

		// Linux drops a held lock before converting it anyway; do it explicitly so the wait below is the only one.
		flock( $this->handle, LOCK_UN );

		$operation = $exclusive ? LOCK_EX : LOCK_SH;
		$start     = microtime( true );
		$delay     = 100000;
		$waited    = false;
		$announced = false;

		while ( ! flock( $this->handle, $operation | LOCK_NB, $would_block ) ) {
			if ( ! $would_block ) {
				EE::warning( sprintf( 'Cannot lock %s, so migrations are not protected against concurrent ee processes.', $this->path ) );
				$this->unavailable = true;
				return $waited;
			}
			$waited  = true;
			$elapsed = microtime( true ) - $start;
			if ( $elapsed >= $this->timeout ) {
				EE::error( sprintf( 'Another ee process is still running EasyEngine migrations (%s). Gave up waiting after %d seconds. Check its progress in %s, then run this command again.', $this->get_holder(), $this->timeout, EE_ROOT_DIR . '/logs/ee.log' ), self::RETRY_EXIT_CODE );
			}
			if ( ! $announced && $elapsed >= self::NOTICE_AFTER ) {
				// STDERR, so `--format=json` output read by scripts stays parseable.
				EE::warning( sprintf( 'Another ee process is running EasyEngine migrations (%s). Waiting up to %d seconds for it to finish...', $this->get_holder(), $this->timeout ) );
				$announced = true;
			}
			usleep( $delay );
			$delay = min( 500000, $delay * 2 );
		}

		if ( $exclusive ) {
			ftruncate( $this->handle, 0 );
			rewind( $this->handle );
			fwrite( $this->handle, sprintf( 'PID %d since %s', getmypid(), gmdate( 'Y-m-d H:i:s' ) . ' UTC' ) );
			fflush( $this->handle );
		}

		return $waited;
	}

	/**
	 * Releases the lock. Safe to call more than once.
	 */
	public function release() {
		if ( $this->handle ) {
			flock( $this->handle, LOCK_UN );
			fclose( $this->handle );
			$this->handle = null;
		}
	}

	/**
	 * @return bool
	 */
	private function open() {
		if ( $this->unavailable ) {
			return false;
		}
		if ( $this->handle ) {
			return true;
		}

		// 'e' (O_CLOEXEC): docker and other children must not keep the lock after ee dies.
		$umask        = umask( 0077 );
		$this->handle = @fopen( $this->path, 'c+e' );
		umask( $umask );

		if ( ! $this->handle ) {
			$this->handle      = null;
			$this->unavailable = true;
			EE::warning( sprintf( 'Cannot open %s, so migrations are not protected against concurrent ee processes.', $this->path ) );
			return false;
		}
		// A readable lock would let any local user stall migrations with a shared lock.
		if ( fileperms( $this->path ) & 0077 ) {
			@chmod( $this->path, 0600 );
		}

		return true;
	}

	/**
	 * @return string The holder recorded by the last exclusive lock, for messages.
	 */
	private function get_holder() {
		$holder = trim( (string) @file_get_contents( $this->path ) );

		return '' === $holder ? 'holder unknown' : $holder;
	}
}
