<?php

// Utilities that do NOT depend on WordPress code.

namespace EE\Utils;

use \Composer\Semver\Comparator;
use \Composer\Semver\Semver;
use \EE\Dispatcher;
use Symfony\Component\Filesystem\Filesystem;

function load_dependencies() {
	$has_autoload = false;

	foreach ( get_vendor_paths() as $vendor_path ) {
		if ( file_exists( $vendor_path . '/autoload.php' ) ) {
			require $vendor_path . '/autoload.php';
			$has_autoload = true;
			break;
		}
	}

	if ( ! $has_autoload ) {
		fputs( STDERR, "Internal error: Can't find Composer autoloader.\nTry running: composer install\n" );
		exit( 3 );
	}
}

function get_vendor_paths() {
	$vendor_paths        = array(
		EE_ROOT . '/../../../vendor',  // part of a larger project / installed via Composer (preferred)
		EE_ROOT . '/vendor',           // top-level project / installed as Git clone
	);
	$maybe_composer_json = EE_ROOT . '/../../../composer.json';
	if ( file_exists( $maybe_composer_json ) && is_readable( $maybe_composer_json ) ) {
		$composer = json_decode( file_get_contents( $maybe_composer_json ) );
		if ( ! empty( $composer->{'vendor-dir'} ) ) {
			array_unshift( $vendor_paths, EE_ROOT . '/../../../' . $composer->{'vendor-dir'} );
		}
	}

	return $vendor_paths;
}

// Using require() directly inside a class grants access to private methods to the loaded code
function load_file( $path ) {
	require_once $path;
}

function load_command( $name ) {
	$path = EE_ROOT . "/php/commands/$name.php";

	if ( is_readable( $path ) ) {
		include_once $path;
	}
}

function load_all_commands() {
	$cmd_dir = EE_ROOT . '/php/commands/';

	$iterator = new \DirectoryIterator( $cmd_dir );

	foreach ( $iterator as $filename ) {
		if ( '.php' != substr( $filename, - 4 ) ) {
			continue;
		}

		include_once "$cmd_dir/$filename";
	}
}

/**
 * Like array_map(), except it returns a new iterator, instead of a modified array.
 *
 * Example:
 *
 *     $arr = array('Football', 'Socker');
 *
 *     $it = iterator_map($arr, 'strtolower', function($val) {
 *       return str_replace('foo', 'bar', $val);
 *     });
 *
 *     foreach ( $it as $val ) {
 *       var_dump($val);
 *     }
 *
 * @param array|object Either a plain array or another iterator
 * @param              callback The function to apply to an element
 *
 * @return object An iterator that applies the given callback(s)
 */
function iterator_map( $it, $fn ) {
	if ( is_array( $it ) ) {
		$it = new \ArrayIterator( $it );
	}

	if ( ! method_exists( $it, 'add_transform' ) ) {
		$it = new Transform( $it );
	}

	foreach ( array_slice( func_get_args(), 1 ) as $fn ) {
		$it->add_transform( $fn );
	}

	return $it;
}

/**
 * Search for file by walking up the directory tree until the first file is found or until $stop_check($dir) returns true
 *
 * @param string|array The files (or file) to search for
 * @param string|null The directory to start searching from; defaults to CWD
 * @param              callable Function which is passed the current dir each time a directory level is traversed
 *
 * @return null|string Null if the file was not found
 */
function find_file_upward( $files, $dir = null, $stop_check = null ) {
	$files = (array) $files;
	if ( is_null( $dir ) ) {
		$dir = getcwd();
	}
	while ( @is_readable( $dir ) ) {
		// Stop walking up when the supplied callable returns true being passed the $dir
		if ( is_callable( $stop_check ) && call_user_func( $stop_check, $dir ) ) {
			return null;
		}

		foreach ( $files as $file ) {
			$path = $dir . DIRECTORY_SEPARATOR . $file;
			if ( file_exists( $path ) ) {
				return $path;
			}
		}

		$parent_dir = dirname( $dir );
		if ( empty( $parent_dir ) || $parent_dir === $dir ) {
			break;
		}
		$dir = $parent_dir;
	}

	return null;
}

function is_path_absolute( $path ) {
	// Windows
	if ( isset( $path[1] ) && ':' === $path[1] ) {
		return true;
	}

	return $path[0] === '/';
}

/**
 * Composes positional arguments into a command string.
 *
 * @param array
 *
 * @return string
 */
function args_to_str( $args ) {
	return ' ' . implode( ' ', array_map( 'escapeshellarg', $args ) );
}

/**
 * Composes associative arguments into a command string.
 *
 * @param array
 *
 * @return string
 */
function assoc_args_to_str( $assoc_args ) {
	$str = '';

	foreach ( $assoc_args as $key => $value ) {
		if ( true === $value ) {
			$str .= " --$key";
		} else {
			$str .= " --$key=" . escapeshellarg( $value );
		}
	}

	return $str;
}

/**
 * Given a template string and an arbitrary number of arguments,
 * returns the final command, with the parameters escaped.
 */
function esc_cmd( $cmd ) {
	if ( func_num_args() < 2 ) {
		trigger_error( 'esc_cmd() requires at least two arguments.', E_USER_WARNING );
	}

	$args = func_get_args();

	$cmd = array_shift( $args );

	return vsprintf( $cmd, array_map( 'escapeshellarg', $args ) );
}

/**
 * Output items in a table, JSON, CSV, ids, or the total count
 *
 * @param string $format Format to use: 'table', 'json', 'csv', 'ids', 'count'
 * @param array $items Data to output
 * @param array|string $fields Named fields for each item of data. Can be array or comma-separated list
 */
function format_items( $format, $items, $fields ) {
	$assoc_args = compact( 'format', 'fields' );
	$formatter  = new \EE\Formatter( $assoc_args );
	$formatter->display_items( $items );
}

/**
 * Pick fields from an associative array or object.
 *
 * @param array|object Associative array or object to pick fields from
 * @param              array List of fields to pick
 *
 * @return array
 */
function pick_fields( $item, $fields ) {
	$item = (object) $item;

	$values = array();

	foreach ( $fields as $field ) {
		$values[ $field ] = isset( $item->$field ) ? $item->$field : null;
	}

	return $values;
}

/**
 * Render PHP or other types of files using Mustache templates.
 *
 * IMPORTANT: Automatic HTML escaping is disabled!
 */
function mustache_render( $template_name, $data ) {
	if ( ! file_exists( $template_name ) ) {
		$template_name = EE_ROOT . "/templates/$template_name";
	}

	$template = file_get_contents( $template_name );

	$m = new \Mustache_Engine( array(
		'escape' => function ( $val ) {
			return $val;
		}
	) );

	return $m->render( $template, $data );
}


function mustache_write_in_file( $filename, $template_name, $data = array() ) {
	$mustache_content = mustache_render( $template_name, $data );
	$filesystem       = new Filesystem();
	$filesystem->dumpFile( $filename, $mustache_content );
}

function make_progress_bar( $message, $count ) {
	if ( \cli\Shell::isPiped() ) {
		return new \EE\NoOp;
	}

	return new \cli\progress\Bar( $message, $count );
}

function parse_url( $url ) {
	$url_parts = \parse_url( $url );

	if ( ! isset( $url_parts['scheme'] ) ) {
		$url_parts = parse_url( 'http://' . $url );
	}

	return $url_parts;
}

/**
 * Check if we're running in a Windows environment (cmd.exe).
 */
function is_windows() {
	return strtoupper( substr( PHP_OS, 0, 3 ) ) === 'WIN';
}

/**
 * Replace magic constants in some PHP source code.
 *
 * @param string $source The PHP code to manipulate.
 * @param string $path The path to use instead of the magic constants
 */
function replace_path_consts( $source, $path ) {
	$replacements = array(
		'__FILE__' => "'$path'",
		'__DIR__'  => "'" . dirname( $path ) . "'"
	);

	$old = array_keys( $replacements );
	$new = array_values( $replacements );

	return str_replace( $old, $new, $source );
}

/**
 * Make a HTTP request to a remote URL
 *
 * @param string $method
 * @param string $url
 * @param array $headers
 * @param array $options
 *
 * @return object
 */
function http_request( $method, $url, $data = null, $headers = array(), $options = array() ) {

	$cert_path = '/rmccue/requests/library/Requests/Transport/cacert.pem';
	if ( inside_phar() ) {
		// cURL can't read Phar archives
		$options['verify'] = extract_from_phar( EE_ROOT . '/vendor' . $cert_path );
	} else {
		foreach ( get_vendor_paths() as $vendor_path ) {
			if ( file_exists( $vendor_path . $cert_path ) ) {
				$options['verify'] = $vendor_path . $cert_path;
				break;
			}
		}
		if ( empty( $options['verify'] ) ) {
			\EE::error_log( "Cannot find SSL certificate." );
		}
	}

	try {
		$request = \Requests::request( $url, $headers, $data, $method, $options );

		return $request;
	} catch ( \Requests_Exception $ex ) {
		// Handle SSL certificate issues gracefully
		\EE::warning( $ex->getMessage() );
		$options['verify'] = false;
		try {
			return \Requests::request( $url, $headers, $data, $method, $options );
		} catch ( \Requests_Exception $ex ) {
			\EE::error( $ex->getMessage() );
		}
	}
}

/**
 * Compare two version strings to get the named semantic version
 *
 * @param string $new_version
 * @param string $original_version
 *
 * @return string $name 'major', 'minor', 'patch'
 */
function get_named_sem_ver( $new_version, $original_version ) {

	if ( ! Comparator::greaterThan( $new_version, $original_version ) ) {
		return '';
	}

	$parts = explode( '-', $original_version );
	list( $major, $minor, $patch ) = explode( '.', $parts[0] );

	if ( Semver::satisfies( $new_version, "{$major}.{$minor}.x" ) ) {
		return 'patch';
	} else if ( Semver::satisfies( $new_version, "{$major}.x.x" ) ) {
		return 'minor';
	} else {
		return 'major';
	}
}

/**
 * Return the flag value or, if it's not set, the $default value.
 *
 * @param array $args Arguments array.
 * @param string $flag Flag to get the value.
 * @param mixed $default Default value for the flag. Default: NULL
 *
 * @return mixed
 */
function get_flag_value( $args, $flag, $default = null ) {
	return isset( $args[ $flag ] ) ? $args[ $flag ] : $default;
}

/**
 * Get the temp directory, and let the user know if it isn't writable.
 *
 * @return string
 */
function get_temp_dir() {
	static $temp = '';

	$trailingslashit = function ( $path ) {
		return rtrim( $path ) . '/';
	};

	if ( $temp ) {
		return $trailingslashit( $temp );
	}

	if ( function_exists( 'sys_get_temp_dir' ) ) {
		$temp = sys_get_temp_dir();
	} else if ( ini_get( 'upload_tmp_dir' ) ) {
		$temp = ini_get( 'upload_tmp_dir' );
	} else {
		$temp = '/tmp/';
	}

	if ( ! @is_writable( $temp ) ) {
		\EE::warning( "Temp directory isn't writable: {$temp}" );
	}

	return $trailingslashit( $temp );
}