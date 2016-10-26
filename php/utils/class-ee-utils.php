<?php

class EE_Utils {

	/**
	 * Unzip/Extract tar/zip/phar files.
	 *
	 * @param string $file          Zip/Tar file location.
	 * @param string $extract_path  Extract location.
	 * @param bool   $overwrite     Overwrite the file or not.
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
	 * Download packages from url.
	 *
	 * @param array $packages Download packages, packges must be array
	 *                         in format of [url, path, package_name]
	 */
	public static function download( $packages ) {
		foreach ( $packages as $package ) {
			$url           = $package['url'];
			$download_path = $package['path'];
			$pkg_name      = $package['package_name'];
			$dirname       = dirname( $download_path );
			$filesystem    = new \Symfony\Component\Filesystem\Filesystem();
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
	public static function validate_domain( $url, $replace_www = true ) {
		$reg_exp = '/https?:\/\//';
		if ( $replace_www ) {
			$reg_exp = '/https?:\/\/|www./';
		}
		$url = preg_replace( $reg_exp, '', $url );
		if ( strpos( $url, '/' ) !== false ) {
			$domain = explode( '/', $url );
			$url    = $domain['0'];
		}

		return $url;
	}

	/**
	 * Generate random string.
	 *
	 * @param int  $length
	 * @param bool $special_chars
	 * @param bool $extra_special_chars
	 *
	 * @return string
	 */
	public static function generate_random( $length = 10, $special_chars = true, $extra_special_chars = false ) {
		$chars = array_merge( range( 'A', 'Z' ), range( 'a', 'z' ), range( '0', '9' ) );
		$chars = implode($chars);
		if ( $special_chars ) {
			$chars .= '!@#$%^&*()';
		}
		if ( $extra_special_chars ) {
			$chars .= '-_ []{}<>~`+=,.;:/?|';
		}

		$password = '';
		for ( $i = 0; $i < $length; $i ++ ) {
			$password .= substr( $chars, rand( 0, strlen( $chars ) - 1 ), 1 );
		}

		return $password;
	}

	/**
	 * TODO: remove if its duplicate.
	 *
	 * Generate random string.
	 *
	 * @return string
	 */
	function random_string( $length = 6 ) {
		$str        = "";
		$characters = array_merge( range( 'A', 'Z' ), range( 'a', 'z' ), range( '0', '9' ) );
		$max        = count( $characters ) - 1;
		for ( $i = 0; $i < $length; $i ++ ) {
			$rand = mt_rand( 0, $max );
			$str .= $characters[ $rand ];
		}

		return $str;
	}
}