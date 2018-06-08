<?php

define( 'EE_ROOT', dirname( dirname( __FILE__ ) ) );

if ( file_exists( EE_ROOT . '/vendor/autoload.php' ) ) {
	define( 'EE_BASE_PATH', EE_ROOT );
	define( 'EE_VENDOR_DIR' , EE_ROOT . '/vendor' );
} elseif ( file_exists( dirname( dirname( EE_ROOT ) ) . '/autoload.php' ) ) {
	define( 'EE_BASE_PATH', dirname( dirname( dirname( EE_ROOT ) ) ) );
	define( 'EE_VENDOR_DIR' , dirname( dirname( EE_ROOT ) ) );
} else {
	fwrite( STDERR, 'Missing vendor/autoload.php' . PHP_EOL );
	exit(1);
}

require EE_VENDOR_DIR . '/autoload.php';
require EE_ROOT . '/php/utils.php';

use Symfony\Component\Finder\Finder;
use EE\Utils;
use EE\Configurator;

$configurator = new Configurator( EE_ROOT . '/utils/make-phar-spec.php' );

list( $args, $assoc_args, $runtime_config ) = $configurator->parse_args( array_slice( $GLOBALS['argv'], 1 ) );

if ( ! isset( $args[0] ) || empty( $args[0] ) ) {
	fwrite( STDERR, "usage: php -dphar.readonly=0 $argv[0] <path> [--quiet] [--version=same|patch|minor|major|x.y.z] [--store-version] [--build=cli]" . PHP_EOL );
	exit(1);
}

define( 'DEST_PATH', $args[0] );

define( 'BE_QUIET', isset( $runtime_config['quiet'] ) && $runtime_config['quiet'] );

define( 'BUILD', isset( $runtime_config['build'] ) ? $runtime_config['build'] : '' );

$current_version = trim( file_get_contents( EE_ROOT . '/VERSION' ) );

if ( isset( $runtime_config['version'] ) ) {
	$new_version = $runtime_config['version'];
	$new_version = Utils\increment_version( $current_version, $new_version );

	if ( isset( $runtime_config['store-version'] ) && $runtime_config['store-version'] ) {
		file_put_contents( EE_ROOT . '/VERSION', $new_version );
	}

	$current_version = $new_version;
}

function add_file( $phar, $path ) {
	$key = str_replace( EE_BASE_PATH, '', $path );

	if ( ! BE_QUIET ) {
		echo "$key - $path" . PHP_EOL;
	}

	$basename = basename( $path );
	if ( 0 === strpos( $basename, 'autoload_' ) && preg_match( '/(?:classmap|files|namespaces|psr4|static)\.php$/', $basename ) ) {
		// Strip autoload maps of unused stuff.
		static $strip_res = null;
		if ( null === $strip_res ) {
			if ( 'cli' === BUILD ) {
				$strips = array(
					'\/(?:behat|composer|gherkin)\/src\/',
					'\/phpunit\/',
					'\/nb\/oxymel\/',
					'-command\/src\/',
					'\/ee\/[^\n]+?-command\/',
					'\/symfony\/(?!finder|polyfill-mbstring)[^\/]+\/',
					'\/(?:dealerdirect|squizlabs|wimg)\/',
				);
			} else {
				$strips = array(
					'\/(?:behat|gherkin)\/src\/',
					'\/phpunit\/',
					'\/symfony\/(?!console|filesystem|finder|polyfill-mbstring|process)[^\/]+\/',
					'\/composer\/spdx-licenses\/',
					'\/Composer\/(?:Command\/|Compiler\.php|Console\/|Downloader\/Pear|Installer\/Pear|Question\/|Repository\/Pear|SelfUpdate\/)',
					'\/(?:dealerdirect|squizlabs|wimg)\/',
				);
			}
			$strip_res = array_map( function ( $v ) {
				return '/^[^,\n]+?' . $v . '[^,\n]+?, *\n/m';
			}, $strips );
		}
		$phar[ $key ] = preg_replace( $strip_res, '', file_get_contents( $path ) );
	} else {
		$phar[ $key ] = file_get_contents( $path );
	}
}

function set_file_contents( $phar, $path, $content ) {
	$key = str_replace( EE_BASE_PATH, '', $path );

	if ( ! BE_QUIET ) {
		echo "$key - $path" . PHP_EOL;
	}

	$phar[ $key ] = $content;
}

function get_composer_versions( $current_version ) {
	$composer_lock_path = EE_ROOT . '/composer.lock';
	if ( ! ( $get_composer_lock = file_get_contents( $composer_lock_path ) ) || ! ( $composer_lock = json_decode( $get_composer_lock, true ) ) ) {
		fwrite( STDERR, sprintf( "Warning: Failed to read '%s'." . PHP_EOL, $composer_lock_path ) );
		return '';
	}
	if ( ! isset( $composer_lock['packages'] ) ) {
		fwrite( STDERR, sprintf( "Warning: No packages in '%s'." . PHP_EOL, $composer_lock_path ) );
		return '';
	}
	$vendor_versions = array( implode( ' ', array( 'easyengine/ee', $current_version, date( 'c' ) ) ) );
	$missing_names = $missing_versions = $missing_references = 0;
	foreach ( $composer_lock['packages'] as $package ) {
		if ( isset( $package['name'] ) ) {
			$vendor_version = array( $package['name'] );
			if ( isset( $package['version'] ) ) {
				$vendor_version[] = $package['version'];
			} else {
				$vendor_version[] = 'unknown_version';
				$missing_versions++;
			}
			if ( isset( $package['source'] ) && isset( $package['source']['reference'] ) ) {
				$vendor_version[] = $package['source']['reference'];
			} elseif( isset( $package['dist'] ) && isset( $package['dist']['reference'] ) ) {
				$vendor_version[] = $package['dist']['reference'];
			} else {
				$vendor_version[] = 'unknown_reference';
				$missing_references++;
			}
			$vendor_versions[] = implode( ' ', $vendor_version );
		} else {
			$vendor_versions[] = implode( ' ', array( 'unknown_package', 'unknown_version', 'unknown_reference' ) );
			$missing_names++;
		}
	}
	if ( $missing_names ) {
		fwrite( STDERR, sprintf( "Warning: %d package names missing from '%s'." . PHP_EOL, $missing_names, $composer_lock_path ) );
	}
	if ( $missing_versions ) {
		fwrite( STDERR, sprintf( "Warning: %d package versions missing from '%s'." . PHP_EOL, $missing_versions, $composer_lock_path ) );
	}
	if ( $missing_references ) {
		fwrite( STDERR, sprintf( "Warning: %d package references missing from '%s'." . PHP_EOL, $missing_references, $composer_lock_path ) );
	}
	return implode( "\n", $vendor_versions );
}

if ( file_exists( DEST_PATH ) ) {
	unlink( DEST_PATH );
}
$phar = new Phar( DEST_PATH, 0, 'ee.phar' );

$phar->startBuffering();

// PHP files
$finder = new Finder();
$finder
	->files()
	->ignoreVCS(true)
	->name('*.php')
	->in(EE_ROOT . '/php')
	->in(EE_VENDOR_DIR . '/mustache')
	->in(EE_VENDOR_DIR . '/rmccue/requests')
	->in(EE_VENDOR_DIR . '/composer')
	->in(EE_VENDOR_DIR . '/ramsey/array_column')
	->in(EE_VENDOR_DIR . '/symfony/finder')
	->in(EE_VENDOR_DIR . '/symfony/polyfill-mbstring')
	->in(EE_VENDOR_DIR . '/monolog')
	->notName('behat-tags.php')
	->notPath('#(?:[^/]+-command|php-cli-tools)/vendor/#') // For running locally, in case have composer installed or symlinked them.
	->exclude('examples')
	->exclude('features')
	->exclude('test')
	->exclude('tests')
	->exclude('Test')
	->exclude('Tests')
	;
if ( 'cli' === BUILD ) {
	$finder
		->in(EE_VENDOR_DIR . '/wp-cli/mustangostang-spyc')
		->in(EE_VENDOR_DIR . '/wp-cli/php-cli-tools')
		->in(EE_VENDOR_DIR . '/seld/cli-prompt')
		->exclude('composer/ca-bundle')
		->exclude('composer/semver')
		->exclude('composer/src')
		->exclude('composer/spdx-licenses')
		;
} else {
	$finder
		->in(EE_VENDOR_DIR . '/easyengine')
		->in(EE_VENDOR_DIR . '/wp-cli')
		->in(EE_VENDOR_DIR . '/psr')
		->in(EE_VENDOR_DIR . '/seld')
		->in(EE_VENDOR_DIR . '/symfony/console')
		->in(EE_VENDOR_DIR . '/symfony/filesystem')
		->in(EE_VENDOR_DIR . '/symfony/process')
		->in(EE_VENDOR_DIR . '/justinrainbow/json-schema')
		->exclude('demo')
		->exclude('nb/oxymel/OxymelTest.php')
		->exclude('composer/spdx-licenses')
		->exclude('composer/composer/src/Composer/Command')
		->exclude('composer/composer/src/Composer/Compiler.php')
		->exclude('composer/composer/src/Composer/Console')
		->exclude('composer/composer/src/Composer/Downloader/PearPackageExtractor.php') // Assuming Pear installation isn't supported by ee.
		->exclude('composer/composer/src/Composer/Installer/PearBinaryInstaller.php')
		->exclude('composer/composer/src/Composer/Installer/PearInstaller.php')
		->exclude('composer/composer/src/Composer/Question')
		->exclude('composer/composer/src/Composer/Repository/Pear')
		->exclude('composer/composer/src/Composer/SelfUpdate')
		;
}

foreach ( $finder as $file ) {
	add_file( $phar, $file );
}

$finder = new Finder();

$finder
	->files()
	->ignoreDotFiles(false)
	->in(EE_VENDOR_DIR . '/easyengine/site-command/templates')
	->name('*.mustache')
	->name('.env.mustache');

foreach ( $finder as $file ) {
	add_file( $phar, $file );
}

// other files
$finder = new Finder();
$finder->files()
	->ignoreVCS(true)
	->ignoreDotFiles(false)
	->in( EE_ROOT . '/templates')
	->in( EE_ROOT . '/ee4-config')
	;

foreach ( $finder as $file ) {
	add_file( $phar, $file );
}

if ( 'cli' !== BUILD ) {
	// Include base project files, because the autoloader will load them
	if ( EE_BASE_PATH !== EE_ROOT ) {
		$finder = new Finder();
		$finder
			->files()
			->ignoreVCS(true)
			->name('*.php')
			->in(EE_BASE_PATH . '/src')
			->exclude('test')
			->exclude('tests')
			->exclude('Test')
			->exclude('Tests');
		foreach ( $finder as $file ) {
			add_file( $phar, $file );
		}
		// Any PHP files in the project root
		foreach ( glob( EE_BASE_PATH . '/*.php' ) as $file ) {
			add_file( $phar, $file );
		}
	}

	foreach ( $finder as $file ) {
		add_file( $phar, $file );
	}
}

add_file( $phar, EE_VENDOR_DIR . '/autoload.php' );
add_file( $phar, EE_VENDOR_DIR . '/autoload_commands.php' );
add_file( $phar, EE_VENDOR_DIR . '/autoload_framework.php' );
if ( 'cli' !== BUILD ) {
	add_file( $phar, EE_VENDOR_DIR . '/composer/composer/LICENSE' );
	add_file( $phar, EE_VENDOR_DIR . '/composer/composer/res/composer-schema.json' );
}
add_file( $phar, EE_VENDOR_DIR . '/rmccue/requests/library/Requests/Transport/cacert.pem' );

set_file_contents( $phar, EE_ROOT . '/COMPOSER_VERSIONS', get_composer_versions( $current_version ) );
set_file_contents( $phar, EE_ROOT . '/VERSION', $current_version );

$phar_boot = str_replace( EE_BASE_PATH, '', EE_ROOT . '/php/boot-phar.php' );
$phar->setStub( <<<EOB
#!/usr/bin/env php
<?php
Phar::mapPhar();
include 'phar://ee.phar{$phar_boot}';
__HALT_COMPILER();
?>
EOB
);

$phar->stopBuffering();

chmod( DEST_PATH, 0755 ); // Make executable.

if ( ! BE_QUIET ) {
	echo "Generated " . DEST_PATH . PHP_EOL;
}
