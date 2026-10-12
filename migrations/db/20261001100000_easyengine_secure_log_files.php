<?php

namespace EE\Migration;

use EE;
use EE\Utils;

/**
 * Makes the EasyEngine log directory private (0700, files 0600) and masks secrets that older versions wrote to it.
 */
class SecureLogFiles extends Base {

	/**
	 * Secrets in the shapes older versions logged them, for values EE no longer knows (deleted sites, changed keys).
	 *
	 * @var array
	 */
	private $patterns;

	/**
	 * What the INFO lines of the invocation being read print: '' (nothing secret), 'value' (`config get <secret-key>`)
	 * or 'keys' (`config list --format=text`).
	 *
	 * @var string
	 */
	private $info_mode = '';

	public function up() {

		$logs_dir = EE_ROOT_DIR . '/logs';
		if ( ! is_dir( $logs_dir ) ) {
			return;
		}

		if ( ! @chmod( $logs_dir, 0700 ) ) {
			EE::warning( "Could not restrict the permissions of $logs_dir to 0700." );
		}

		$this->register_known_secrets();
		$this->patterns = $this->get_old_log_patterns();

		$files = scandir( $logs_dir );
		if ( false === $files ) {
			return;
		}

		// The active log last: this process appends to it until then.
		usort(
			$files,
			function ( $a, $b ) {
				return ( 'ee.log' === $a ) - ( 'ee.log' === $b );
			}
		);

		foreach ( $files as $file ) {
			$path = $logs_dir . '/' . $file;
			if ( '.' === $file || '..' === $file || is_link( $path ) || ! is_file( $path ) ) {
				continue;
			}

			if ( ! @chmod( $path, 0600 ) ) {
				EE::warning( "Could not restrict the permissions of $path to 0600." );
			}

			if ( preg_match( '/\.log(\.\d+)?(\.gz)?$/', $file ) ) {
				try {
					$this->scrub_file( $path );
				} catch ( \Throwable $e ) {
					@unlink( $path . '.ee-scrub' );
					EE::warning( "Could not remove secrets from $path: " . $e->getMessage() );
				}
			}
		}
	}

	/**
	 * Nothing to undo: loosening the permissions or restoring secrets is never wanted.
	 */
	public function down() {
	}

	/**
	 * Mask the secrets EE still knows: site and auth passwords from the database and secret config values.
	 */
	private function register_known_secrets() {
		$queries = [
			[ 'sites', [ 'db_password', 'db_root_password', 'app_admin_password' ] ],
			[ 'auth_users', [ 'password' ] ],
		];
		foreach ( $queries as $query ) {
			try {
				$rows = EE::db()->table( $query[0] )->select( ...$query[1] )->get();
			} catch ( \Throwable $e ) {
				continue;
			}
			foreach ( (array) $rows as $row ) {
				EE::obfuscate( Utils\get_secret_values( $row ) );
			}
		}

		EE::obfuscate( Utils\get_secret_values( Utils\get_config_file_values() ) );
	}

	/**
	 * Patterns for the other shapes in which older versions logged secrets, beyond the flags and JSON members that
	 * EE::mask_secrets() already handles.
	 *
	 * @return array Regexes whose group 1 is kept and group 2 masked.
	 */
	private function get_old_log_patterns() {
		$key   = Utils\secret_key_regex();
		$quote = '\'(?:\\\\.|[^\'\\\\]++)*+\'|"(?:\\\\.|[^"\\\\]++)*+"';

		return [
			// `ee config set cloudflare-api-key <key>`, `wp config set DB_PASSWORD <password>`.
			'/(\bconfig\s+set\s+[\'"]?(?:' . $key . ')[\'"]?\s+)(' . $quote . '|\S+)/i',
			// A failed `CREATE USER … IDENTIFIED BY '<password>'` echoed by the mysql client.
			'/(\bIDENTIFIED\s+BY\s+(?:PASSWORD\s+)?)(' . $quote . '|\S+)/i',
			// `wp config set DB_PASSWORD …` output during a restore.
			'/(\bconstant\s+\'\w*(?:PASSWORD|_KEY|SALT)\w*\'.*?\bvalue\s+)(\'[^\r\n]*\')/i',
			// EasyDash backup debug lines and the logged callback payload.
			'/((?:\bverify_token:|--dash-auth value:)\s*)(\S+)/',
			'/("verify"\s*:\s*)("(?:\\\\.|[^"\\\\]++)*+")/',
			// The weak --admin-pass error repeated the password.
			'/(Issues found in input password: `)([^`]*+)/',
		];
	}

	/**
	 * Rewrite a log file with its secrets masked: a masked copy is written next to it, checked, and renamed over it.
	 *
	 * @param string $path Log file.
	 *
	 * @throws \Exception When the file can't be read or the copy can't be written completely.
	 */
	private function scrub_file( $path ) {
		$gz  = '.gz' === substr( $path, -3 );
		$tmp = $path . '.ee-scrub';

		$umask = umask( 0077 );
		$in    = $gz ? gzopen( $path, 'rb' ) : fopen( $path, 'rb' );
		$out   = $gz ? gzopen( $tmp, 'wb6' ) : fopen( $tmp, 'wb' );
		umask( $umask );
		if ( ! $in || ! $out ) {
			throw new \Exception( 'unable to open the file or its temporary copy' );
		}

		$changed         = false;
		$lines           = 0;
		$this->info_mode = '';
		while ( true ) {
			$line = $gz ? gzgets( $in ) : fgets( $in );
			if ( false === $line ) {
				break;
			}
			$lines++;
			$masked  = $this->mask_line( $line );
			$changed = $changed || $masked !== $line;
			$written = $gz ? gzwrite( $out, $masked ) : fwrite( $out, $masked );
			if ( strlen( $masked ) !== $written ) {
				throw new \Exception( 'unable to write the temporary copy' );
			}
		}
		$gz ? gzclose( $in ) : fclose( $in );
		if ( ! ( $gz ? gzclose( $out ) : fclose( $out ) ) ) {
			throw new \Exception( 'unable to write the temporary copy' );
		}

		if ( ! $changed ) {
			unlink( $tmp );

			return;
		}

		// Read the copy back, so a full disk can't replace a log with a truncated one.
		if ( $this->count_lines( $tmp, $gz ) !== $lines ) {
			throw new \Exception( 'the temporary copy is incomplete' );
		}

		touch( $tmp, filemtime( $path ) );
		// The umask above doesn't apply under a default ACL on the logs dir.
		if ( ! chmod( $tmp, 0600 ) ) {
			throw new \Exception( 'unable to restrict the permissions of the temporary copy' );
		}
		if ( ! rename( $tmp, $path ) ) {
			throw new \Exception( 'unable to replace the file' );
		}

		// This process may hold the old ee.log open; its handler reopens the path on the next write.
		if ( EE_ROOT_DIR . '/logs/ee.log' === $path && EE::get_file_logger() ) {
			foreach ( EE::get_file_logger()->getHandlers() as $handler ) {
				$handler->close();
			}
		}
		EE::debug( "Removed secrets from $path." );
	}

	/**
	 * @param string $path File.
	 * @param bool   $gz   Whether it is gzip-compressed.
	 *
	 * @return int Number of lines, or -1 when it can't be read.
	 */
	private function count_lines( $path, $gz ) {
		$fh = $gz ? gzopen( $path, 'rb' ) : fopen( $path, 'rb' );
		if ( ! $fh ) {
			return -1;
		}
		$lines = 0;
		while ( false !== ( $gz ? gzgets( $fh ) : fgets( $fh ) ) ) {
			$lines++;
		}
		$gz ? gzclose( $fh ) : fclose( $fh );

		return $lines;
	}

	/**
	 * @param string $line Log line.
	 *
	 * @return string
	 */
	private function mask_line( $line ) {
		// Older `config get`/`config list` logged values on their own INFO lines, recognisable only from the invocation's argv line.
		if ( false !== strpos( $line, '::::ee invoked::::' ) ) {
			$this->info_mode = '';
		} elseif ( preg_match( '/\.DEBUG: argv: .*?\bconfig\s+(get|list)\b(?:\s+(\S+))?/', $line, $matches ) ) {
			if ( 'list' === $matches[1] ) {
				$this->info_mode = 'keys';
			} elseif ( isset( $matches[2] ) && Utils\is_secret_key( $matches[2] ) ) {
				$this->info_mode = 'value';
			}
		} elseif ( 'value' === $this->info_mode ) {
			$line = $this->replace( '/^(\[[^\]]*\] [\w-]+\.INFO: ).*?(\s*)$/', '$1******$2', $line );
		} elseif ( 'keys' === $this->info_mode ) {
			$line = $this->replace( '/^(\[[^\]]*\] [\w-]+\.INFO: (?:' . Utils\secret_key_regex() . '): ).*?(\s*)$/i', '$1******$2', $line );
		}

		$line = EE::mask_secrets( $line );
		foreach ( $this->patterns as $pattern ) {
			$line = $this->replace( $pattern, '$1******', $line );
		}

		return $line;
	}

	/**
	 * preg_replace() that keeps the line when the regex fails (e.g. on a huge line).
	 *
	 * @param string $pattern     Regex.
	 * @param string $replacement Replacement.
	 * @param string $line        Log line.
	 *
	 * @return string
	 */
	private function replace( $pattern, $replacement, $line ) {
		$masked = preg_replace( $pattern, $replacement, $line );

		return null === $masked ? $line : $masked;
	}
}
