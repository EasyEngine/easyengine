<?php

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;

class EE_OS {

	public static function ee_platform_codename() {
		$os_codename = EE_CLI::exec_cmd_output( "lsb_release -sc" );

		return $os_codename;
	}

	public static function ee_platform_distro() {
		$os_distro = EE_CLI::exec_cmd_output( "lsb_release -si" );

		return $os_distro;
	}

	public static function ee_platform_version() {
		$os_version = EE_CLI::exec_cmd_output( "lsb_release -sr" );

		return $os_version;
	}

	public static function ee_core_version() {
		$ee_version = EE_CLI_VERSION;

		return $ee_version;
	}

	public static function ee_wpcli_version() {
		$ee_version_check = EE_CLI::exec_cmd_output( "wp --version | awk '{print $2}' | cut -d'-' -f1" );
		if ( empty( $ee_version_check ) ) {
			$ee_version = EE_WP_CLI;
		} else {
			$ee_version = $ee_version_check;
		}

		return $ee_version;
	}

	public static function extract( $file, $extract_path, $overwrite = false ) {
		try {
			$phar = new PharData( $file );
			$phar->extractTo( $extract_path, null, $overwrite );

			return true;
		} catch ( Exception $e ) {
			// handle errors
			EE_CLI::debug( $e->getMessage() );
			EE_CLI::error( "Unable to extract file " . $file );

			return false;
		}
	}

	/**
	 * @param array $packages Download packages, packges must be array in format of [url, path, package_name]
	 */
	public static function download( $packages ) {
		foreach ( $packages as $package ) {
			$url           = $package['url'];
			$download_path = $package['path'];
			$pkg_name      = $package['package_name'];
			$dirname       = dirname( $download_path );
			$filesystem    = new Filesystem();
			if ( ! $filesystem->exists( $dirname ) ) {
				try {
					$filesystem->mkdir( $dirname );
				} catch ( IOExceptionInterface $e ) {
					echo "An error occurred while creating your directory at " . $e->getPath();
				}
			}
			try {
				EE_CLI::log( "Downloading " . $pkg_name );
				set_time_limit( 0 ); // unlimited max execution time
				$options = array(
					CURLOPT_FILE => $download_path,
					CURLOPT_URL  => $url,
				);

				$ch = curl_init();
				curl_setopt_array( $ch, $options );
				curl_exec( $ch );
				curl_close( $ch );
				EE_CLI::log( "[Done]" );
			} catch ( Exception $e ) {
				EE_CLI::debug( $e->getMessage() );
				EE_CLI::error( "Unable to download " . $pkg_name );
			}
		}
	}

	/**
	 * This function returns domain name removing http:// and https://
	 * returns domain name only with or without www as user provided.
	 *
	 * @param $url
	 *
	 * @return mixed
	 */
	public static function validate_domain( $url ) {
		$url = preg_replace( '/https?:\/\/|www./', '', $url );
		if ( strpos( $url, '/' ) !== false ) {
			$domain = explode( '/', $url );
			$url    = $domain['0'];
		}

		return $url;
	}
}
