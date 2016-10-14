<?php

class EE_Utils {
	
	/**
	 * @param      $file
	 * @param      $extract_path
	 * @param bool $overwrite
	 *
	 * @return bool
	 */
	public static function extract( $file, $extract_path, $overwrite = false ) {
		try {
			$phar = new PharData( $file );
			$phar->extractTo( $extract_path, null, $overwrite );

			return true;
		} catch ( Exception $e ) {
			// handle errors
			EE::debug( $e->getMessage() );
			EE::error( "Unable to extract file " . $file );

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
				EE::log( "Downloading " . $pkg_name );
				set_time_limit( 0 ); // unlimited max execution time
				$options = array(
					CURLOPT_FILE => $download_path,
					CURLOPT_URL  => $url,
				);

				$ch = curl_init();
				curl_setopt_array( $ch, $options );
				curl_exec( $ch );
				curl_close( $ch );
				EE::log( "[Done]" );
			} catch ( Exception $e ) {
				EE::debug( $e->getMessage() );
				EE::error( "Unable to download " . $pkg_name );
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