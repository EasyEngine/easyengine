<?php

namespace EE;

/**
 * The PHP extensions and functions EasyEngine needs from the PHP that runs it.
 *
 * PHP 8 removes the functions listed in `disable_functions`, so calling one is a fatal "Call to undefined function".
 * This class only probes with function_exists(), extension_loaded() and guarded calls, so it can't hit one itself.
 */
class Requirements {

	/**
	 * Functions EasyEngine calls without a guard, by the extension that provides them.
	 *
	 * Without one of them a command fails part way, so a missing one refuses every command up front.
	 * json isn't listed: the logger needs it while booting, before this check can run.
	 */
	const FUNCTIONS = [
		'standard' => [ 'chgrp', 'chown', 'disk_free_space', 'escapeshellarg', 'exec', 'gethostname', 'getmypid', 'parse_ini_string', 'php_uname', 'proc_close', 'proc_open', 'stream_select', 'stream_set_blocking', 'symlink' ],
		'curl'     => [ 'curl_close', 'curl_errno', 'curl_error', 'curl_exec', 'curl_getinfo', 'curl_init', 'curl_setopt', 'curl_version' ],
		'openssl'  => [ 'openssl_pkey_get_private', 'openssl_x509_check_private_key', 'openssl_x509_parse' ],
		'posix'    => [ 'posix_getegid', 'posix_geteuid', 'posix_getpwuid' ],
		'zlib'     => [ 'gzclose', 'gzeof', 'gzgets', 'gzopen', 'gzwrite' ],
	];

	/**
	 * Extensions used through classes (PDO, ZipArchive) rather than functions.
	 */
	const EXTENSIONS = [ 'pdo_sqlite', 'zip' ];

	/**
	 * What running external commands (EE::exec(), EE::launch()) needs.
	 */
	const PROCESS_FUNCTIONS = [ 'proc_close', 'proc_open', 'stream_select', 'stream_set_blocking' ];

	/**
	 * Functions EasyEngine uses only when available, with what is lost without them.
	 */
	const OPTIONAL_FUNCTIONS = [
		'pcntl_signal'          => 'site commands interrupted by a signal (e.g. Ctrl+C) are not rolled back',
		'pcntl_signal_dispatch' => 'Ctrl+C while waiting for another SSL operation takes effect only after the wait',
		'shell_exec'            => '`ee site share` does not work',
	];

	/**
	 * Debian/Ubuntu package suffixes (php<ver>-<suffix>) of extensions that aren't built into php-cli there.
	 */
	const DEB_PACKAGES = [
		'curl'       => 'curl',
		'mbstring'   => 'mbstring',
		'pdo_sqlite' => 'sqlite3',
		'posix'      => 'common',
		'zip'        => 'zip',
	];

	/**
	 * Find the required extensions and functions this PHP lacks.
	 *
	 * @param string[]|null $only Extension or function names to check, null for everything EasyEngine needs.
	 *
	 * @return array {
	 *     @type string[] $extensions Extensions that aren't loaded.
	 *     @type string[] $disabled   Functions listed in `disable_functions`.
	 *     @type string[] $missing    Functions missing for another reason (e.g. a PHP build without them).
	 * }
	 */
	public static function get_missing( $only = null ) {

		$missing = [
			'extensions' => [],
			'disabled'   => [],
			'missing'    => [],
		];
		$wanted  = function ( $name ) use ( $only ) {
			return null === $only || in_array( $name, $only, true );
		};

		foreach ( self::FUNCTIONS as $extension => $functions ) {
			if ( ! extension_loaded( $extension ) ) {
				if ( $wanted( $extension ) || array_filter( $functions, $wanted ) ) {
					$missing['extensions'][] = $extension;
				}
				continue;
			}
			foreach ( $functions as $function ) {
				if ( ( $wanted( $function ) || $wanted( $extension ) ) && ! function_exists( $function ) ) {
					$missing[ self::is_disabled( $function ) ? 'disabled' : 'missing' ][] = $function;
				}
			}
		}

		foreach ( self::EXTENSIONS as $extension ) {
			if ( $wanted( $extension ) && ! extension_loaded( $extension ) ) {
				$missing['extensions'][] = $extension;
			}
		}

		// Symfony's mbstring polyfill, used for all table output, needs iconv.
		if ( $wanted( 'mbstring' ) && ! extension_loaded( 'mbstring' ) && ! extension_loaded( 'iconv' ) ) {
			$missing['extensions'][] = 'mbstring';
		}

		// Names this class doesn't list are checked as functions, so a caller can't get a false OK.
		$known = array_merge( array_keys( self::FUNCTIONS ), self::EXTENSIONS, [ 'mbstring' ], call_user_func_array( 'array_merge', array_values( self::FUNCTIONS ) ) );
		foreach ( array_diff( (array) $only, $known ) as $function ) {
			if ( ! function_exists( $function ) ) {
				$missing[ self::is_disabled( $function ) ? 'disabled' : 'missing' ][] = $function;
			}
		}

		return $missing;
	}

	/**
	 * Whether get_missing() found anything.
	 *
	 * @param array $missing Result of get_missing().
	 *
	 * @return bool
	 */
	public static function has_missing( $missing ) {
		return ! empty( $missing['extensions'] ) || ! empty( $missing['disabled'] ) || ! empty( $missing['missing'] );
	}

	/**
	 * Summarize what get_missing() found in one line, e.g. for `ee cli info`.
	 *
	 * @param array $missing Result of get_missing().
	 *
	 * @return string Empty when nothing is missing.
	 */
	public static function get_summary( $missing ) {
		$labels = [
			'extensions' => 'missing extensions',
			'disabled'   => 'disabled functions',
			'missing'    => 'missing functions',
		];
		$parts  = [];
		foreach ( $labels as $key => $label ) {
			if ( ! empty( $missing[ $key ] ) ) {
				$parts[] = $label . ': ' . implode( ', ', $missing[ $key ] );
			}
		}

		return ucfirst( implode( '; ', $parts ) );
	}

	/**
	 * Find the optional functions this PHP lacks.
	 *
	 * @return string[] What is lost without each, keyed by function name.
	 */
	public static function get_missing_optional() {
		$missing = [];
		foreach ( self::OPTIONAL_FUNCTIONS as $function => $impact ) {
			// Without pcntl_signal() no handler waits to be dispatched, so Ctrl+C ends the wait at once.
			if ( 'pcntl_signal_dispatch' === $function && ! function_exists( 'pcntl_signal' ) ) {
				continue;
			}
			if ( ! function_exists( $function ) ) {
				$missing[ $function ] = $impact;
			}
		}

		return $missing;
	}

	/**
	 * Get the functions listed in `disable_functions`.
	 *
	 * @return string[]
	 */
	public static function get_disabled_functions() {
		if ( function_exists( 'ini_get' ) ) {
			$value = ini_get( 'disable_functions' );
		} elseif ( function_exists( 'get_cfg_var' ) ) {
			$value = get_cfg_var( 'disable_functions' );
		} else {
			return [];
		}

		// PHP splits the list on spaces and commas only.
		return array_values( array_filter( preg_split( '/[ ,]+/', (string) $value ) ) );
	}

	/**
	 * Whether a function is listed in `disable_functions`.
	 *
	 * @param string $function Function name.
	 *
	 * @return bool
	 */
	private static function is_disabled( $function ) {
		return in_array( strtolower( $function ), array_map( 'strtolower', self::get_disabled_functions() ), true );
	}

	/**
	 * Get the loaded ini files, in the order PHP reads them.
	 *
	 * @return string[]
	 */
	private static function get_ini_files() {
		$files = [];
		if ( function_exists( 'php_ini_loaded_file' ) ) {
			$files[] = (string) php_ini_loaded_file();
		}
		if ( function_exists( 'php_ini_scanned_files' ) ) {
			$files = array_merge( $files, explode( ',', (string) php_ini_scanned_files() ) );
		}

		return array_values( array_filter( array_map( 'trim', $files ) ) );
	}

	/**
	 * Get the loaded ini files whose `disable_functions` lists any of the given functions.
	 *
	 * @param string[] $functions Function names.
	 *
	 * @return string[]
	 */
	public static function get_disabling_ini_files( $functions ) {
		$functions = array_map( 'strtolower', $functions );
		$disabling = [];
		foreach ( self::get_ini_files() as $file ) {
			$content = function_exists( 'file_get_contents' ) && is_readable( $file ) ? (string) file_get_contents( $file ) : '';
			if ( ! preg_match_all( '/^[ \t]*disable_functions[ \t]*=(.*)$/m', $content, $matches ) ) {
				continue;
			}
			// The last directive in a file is the one that counts; `;` starts a comment.
			$value  = preg_replace( '/;.*$/', '', end( $matches[1] ) );
			$listed = array_map( 'strtolower', preg_split( '/[ ,]+/', trim( $value, " \t\r\"'" ) ) );
			if ( array_intersect( $functions, $listed ) ) {
				$disabling[] = $file;
			}
		}

		return $disabling;
	}

	/**
	 * Build the error message for what get_missing() found, with how to fix it.
	 *
	 * @param array $missing Result of get_missing().
	 *
	 * @return string
	 */
	public static function get_error_message( $missing ) {

		$php      = sprintf( 'PHP %s (%s)', PHP_VERSION, PHP_BINARY );
		$messages = [];

		if ( ! empty( $missing['disabled'] ) ) {
			$files      = self::get_disabling_ini_files( $missing['disabled'] );
			$messages[] = sprintf(
				'EasyEngine needs the PHP %s %s, which %s disables with disable_functions in %s.',
				self::plural( $missing['disabled'], 'function', 'functions' ),
				implode( ', ', $missing['disabled'] ),
				$php,
				empty( $files ) ? 'its configuration (see `php --ini`)' : implode( ', ', $files )
			);
			$messages[] = sprintf( 'Remove %s from disable_functions for the PHP CLI (if the web server\'s PHP has its own configuration, that can stay as it is).', self::plural( $missing['disabled'], 'it', 'them' ) ) . self::get_override_hint( $missing['disabled'], $files );
		}

		if ( ! empty( $missing['extensions'] ) ) {
			$messages[] = sprintf(
				'EasyEngine needs the PHP %s %s, which %s does not load. %s',
				self::plural( $missing['extensions'], 'extension', 'extensions' ),
				implode( ', ', $missing['extensions'] ),
				$php,
				self::get_extension_hint( $missing['extensions'] )
			);
		}

		if ( ! empty( $missing['missing'] ) ) {
			$messages[] = sprintf(
				'EasyEngine needs the PHP %s %s, which %s lacks. Use a PHP build that includes %s.',
				self::plural( $missing['missing'], 'function', 'functions' ),
				implode( ', ', $missing['missing'] ),
				$php,
				self::plural( $missing['missing'], 'it', 'them' )
			);
		}

		return implode( PHP_EOL, $messages );
	}

	/**
	 * Suggest an override file in the scan directory, which survives tools that rewrite php.ini.
	 *
	 * @param string[] $disabled Required functions that are disabled.
	 * @param string[] $files    Ini files that disable them.
	 *
	 * @return string Empty when no override file would be read last.
	 */
	private static function get_override_hint( $disabled, $files ) {
		$scanned  = array_slice( self::get_ini_files(), function_exists( 'php_ini_loaded_file' ) && php_ini_loaded_file() ? 1 : 0 );
		$scan_dir = empty( $scanned ) ? self::get_scan_dir() : dirname( end( $scanned ) );
		// Without a disabling ini file the value comes from `php -d`, which no ini file overrides.
		if ( '' === $scan_dir || empty( $files ) ) {
			return '';
		}

		$name = '99-easyengine.ini';
		foreach ( $files as $file ) {
			// A disabling file read after the override would win, so it has to be edited instead.
			if ( in_array( $file, $scanned, true ) && strcmp( basename( $file ), $name ) > 0 ) {
				return '';
			}
		}
		$keep = array_diff( self::get_disabled_functions(), $disabled );

		return sprintf(
			' Tools that manage php.ini may rewrite it, so an override read after it keeps the change, e.g. %s/%s containing: disable_functions = %s',
			rtrim( $scan_dir, '/' ),
			$name,
			empty( $keep ) ? '""' : implode( ',', $keep )
		);
	}

	/**
	 * Get the directory PHP scans last for ini files.
	 *
	 * @return string Empty when PHP scans none.
	 */
	private static function get_scan_dir() {
		$builtin = defined( 'PHP_CONFIG_FILE_SCAN_DIR' ) ? PHP_CONFIG_FILE_SCAN_DIR : '';
		$env     = function_exists( 'getenv' ) ? getenv( 'PHP_INI_SCAN_DIR' ) : ( isset( $_SERVER['PHP_INI_SCAN_DIR'] ) ? $_SERVER['PHP_INI_SCAN_DIR'] : false );
		// PHP_INI_SCAN_DIR replaces the built-in directory: empty turns scanning off, an empty entry in a list stands for the built-in one.
		if ( false === $env || '' === $env ) {
			return false === $env ? $builtin : '';
		}
		$dirs = explode( PATH_SEPARATOR, $env );
		$last = end( $dirs );

		return '' === $last ? $builtin : $last;
	}

	/**
	 * Get a hint on how to install missing extensions.
	 *
	 * @param string[] $extensions Missing extensions.
	 *
	 * @return string
	 */
	private static function get_extension_hint( $extensions ) {
		$it = self::plural( $extensions, 'it', 'them' );
		if ( 'Darwin' === PHP_OS ) {
			return sprintf( 'Install or enable %s, or use a PHP that includes %s, e.g. Homebrew\'s (`brew install php`).', $it, $it );
		}

		$packages = [];
		foreach ( $extensions as $extension ) {
			if ( isset( self::DEB_PACKAGES[ $extension ] ) ) {
				$packages[] = 'php' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '-' . self::DEB_PACKAGES[ $extension ];
			}
		}

		return sprintf( 'Install or enable %s for this PHP%s.', $it, empty( $packages ) ? '' : ', e.g. on Debian/Ubuntu: `apt-get install ' . implode( ' ', $packages ) . '`' );
	}

	/**
	 * Pick the singular or plural word for a list.
	 *
	 * @param array  $items    List.
	 * @param string $singular Word for one item.
	 * @param string $plural   Word for more.
	 *
	 * @return string
	 */
	private static function plural( $items, $singular, $plural ) {
		return 1 === count( $items ) ? $singular : $plural;
	}
}
