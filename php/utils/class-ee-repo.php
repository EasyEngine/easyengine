<?php
class EE_Repo {
	/**
	 * This function used to add apt repositories and or ppa's
	 * If repo_url is provided adds repo file to
	 *      /etc/apt/sources.list.d/
	 * If ppa is provided add apt-repository using
	 *      add-apt-repository
	 * command.
	 *
	 * @param string $repo_url
	 * @param string $ppa
	 */
	public static function add( $repo_url = '', $ppa = '' ) {

		if ( ! empty( $repo_url ) ) {
			$repo_file_path = EE_Variables::get_ee_repo_file_path();
			EE:
			try {
				if ( file_exists( $repo_file_path ) ) {
					$repo_file_contents = file_get_contents( $repo_file_path );
					$repo_file_content  = explode( "\n", $repo_file_contents );
					if ( in_array( $repo_url, $repo_file_content, true ) ) {
						EE::log( "Entry Already Exists." );
					} else {
						$repo_file = fopen( $repo_file_path, 'a+' );
						fwrite( $repo_file, $repo_url . "\n" );
						fclose( $repo_file );
					}
				} else {
					$repo_file = fopen( $repo_file_path, 'w' );
					fwrite( $repo_file, $repo_url . "\n" );
					fclose( $repo_file );
				}
			} catch ( Exception $e ) {
				EE::error( $e->getMessage() );
			}
		}

		if ( ! empty( $ppa ) ) {
			$add_ppa = EE::exec_cmd( "add-apt-repository -y " . $ppa );
			if ( 0 == $add_ppa ) {
				EE::success( 'Repository added successfully.' );
			} else {
				EE::error( 'Repository couldn\'t added. Please try again.' );
			}
		}
	}

	/**
	 * This function used to remove ppa's
	 * If ppa is provided adds repo file to
	 *      /etc/apt/sources.list.d/
	 * command.
	 *
	 * @param string $repo_url
	 * @param string $ppa
	 */
	public static function remove( $repo_url = '', $ppa = '' ) {

		if ( ! empty( $repo_url ) ) {
			$repo_file_path = EE_Variables::get_ee_repo_file_path();
			try {
				if ( file_exists( $repo_file_path ) ) {
					$repo_file_contents = file_get_contents( $repo_file_path );
					$repo_file_content  = explode( "\n", $repo_file_contents );

					if ( in_array( $repo_url, $repo_file_content, true ) ) {
						$key = array_search( $repo_url, $repo_file_content );
						unset( $repo_file_content[ $key ] );
						$repo_file                 = fopen( $repo_file_path, 'w' );
						$updated_repo_file_content = implode( "\n", $repo_file_content );
						fwrite( $repo_file, $updated_repo_file_content );
						fclose( $repo_file );
					} else {
						EE::error( "Repo url not found in list." );
					}
				} else {
					EE::error( "Repo file does not exist." );
				}
			} catch ( Exception $e ) {
				EE::error( $e->getMessage() );
			}
		}

		if ( ! empty( $ppa ) ) {
			$remove_ppa = EE::exec_cmd( "add-apt-repository -y --remove " . $ppa );
			if ( 0 == $remove_ppa ) {
				EE::success( 'Repository added successfully.' );
			} else {
				EE::error( 'Repository couldn\'t added. Please try again.' );
			}
		}
	}

	/**
	 * This function adds imports repository keys from keyserver.
	 * default keyserver is hkp://keys.gnupg.net
	 * user can provide other keyserver with keyserver="hkp://xyz"
	 *
	 * @param        $keyids
	 * @param string $keyserver
	 */
	public static function add_key( $keyids, $keyserver = "hkp://keys.gnupg.net" ) {
		if ( ! empty( $keyids ) ) {
			// TODO: Check what this command does.
			$add_key = EE::exec_cmd( "gpg --keyserver " . $keyserver . "  --recv-keys " . $keyids );
			if ( 0 == $add_key ) {
				// TODO: Check what this command does.
				$export_add_key = EE::exec_cmd( "gpg -a --export --armor " . $keyids . " | apt-key add - " );
				if ( 0 == $export_add_key ) {
					EE::success( 'GPG key added successfully.' );
				} else {
					EE::error( 'GPG key could not add.' );
				}
			} else {
				EE::error( 'GPG key could not add.' );
			}
		}
	}
}