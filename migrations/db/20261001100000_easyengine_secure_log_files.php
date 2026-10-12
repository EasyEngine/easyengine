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

		// The active log last: this process appends to it.
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

		$config_file = getenv( 'EE_CONFIG_PATH' ) ? getenv( 'EE_CONFIG_PATH' ) : EE_ROOT_DIR . '/config/config.yml';
		if ( is_file( $config_file ) && is_readable( $config_file ) ) {
			EE::obfuscate( Utils\get_secret_values( (array) \Mustangostang\Spyc::YAMLLoad( $config_file ) ) );
		}
	}

	/**
	 * Patterns for the other shapes in which older versions logged secrets, beyond the flags and JSON members that
	 * EE::mask_secrets() already handles.
	 *
	 * @return array Regexes whose group 1 is kept and group 2 masked.
	 */
	private function get_old_log_patterns() {
		$key   = Utils\secret_key_regex();
		$quote = '\'(?:\\\\.|[^\'\\\\])*\'|"(?:\\\\.|[^"\\\\])*"';

		return [
			// `ee config set cloudflare-api-key <key>` in the argv line.
			'/(\bconfig\s+set\s+(?:' . $key . ')\s+)(\S+)/i',
			// A failed `CREATE USER … IDENTIFIED BY '<password>'` echoed by the mysql client.
			'/(\bIDENTIFIED\s+BY\s+(?:PASSWORD\s+)?)(' . $quote . '|\S+)/i',
			// `wp config set DB_PASSWORD …` output during a restore.
			'/(\bconstant\s+\'\w*(?:PASSWORD|_KEY|SALT)\w*\'.*?\bvalue\s+)(\'[^\r\n]*\')/i',
			// mysql -p'<password>'.
			'/((?<![\w-])-p)(' . $quote . ')/',
			// openssl -passout pass:<password>.
			'/(-pass(?:in|out)\s+pass:)(' . $quote . '|\S+)/',
		];
	}

	/**
	 * Rewrite a log file with its secrets masked. Plain files keep their inode (this process may hold ee.log open),
	 * rotated .gz files are replaced.
	 *
	 * @param string $path Log file.
	 *
	 * @throws \Exception When the file can't be read or written.
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
		$this->info_mode = '';
		while ( true ) {
			$line = $gz ? gzgets( $in ) : fgets( $in );
			if ( false === $line ) {
				break;
			}
			$masked  = $this->mask_line( $line );
			$changed = $changed || $masked !== $line;
			if ( false === ( $gz ? gzwrite( $out, $masked ) : fwrite( $out, $masked ) ) ) {
				throw new \Exception( 'unable to write the temporary copy' );
			}
		}
		$gz ? gzclose( $in ) : fclose( $in );
		$gz ? gzclose( $out ) : fclose( $out );

		if ( ! $changed ) {
			unlink( $tmp );

			return;
		}

		if ( $gz ) {
			touch( $tmp, filemtime( $path ) );
			if ( ! rename( $tmp, $path ) ) {
				unlink( $tmp );
				throw new \Exception( 'unable to replace the file' );
			}
		} else {
			$mtime = filemtime( $path );
			$src   = fopen( $tmp, 'rb' );
			$dst   = fopen( $path, 'r+b' );
			if ( ! $src || ! $dst || ! ftruncate( $dst, 0 ) || false === stream_copy_to_stream( $src, $dst ) ) {
				throw new \Exception( 'unable to rewrite the file' );
			}
			fclose( $src );
			fclose( $dst );
			unlink( $tmp );
			touch( $path, $mtime );
		}
		EE::debug( "Removed secrets from $path." );
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
