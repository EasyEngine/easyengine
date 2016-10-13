<?php
class EE_APT_GET {

	/**
	 * Update cache.
	 */
	public static function update() {
		$update_cmd = "apt-get update";
		$update = EE::exec_cmd( $update_cmd, 'apt-get update' );
		if ( 0 == $update ) {
			EE::success( 'Cache updated successfully.' );
		} else {
			EE::success( 'Oops Something went wrong!!' );
		}
	}

	/**
	 *  Similar to `apt-get upgrade`
	 */
	public static function check_upgrade() {
		$check_upgrade_cmd = 'apt-get upgrade -s | grep \"^Inst\" | wc -l';
		$check_upgrade = EE::exec_cmd( $check_upgrade_cmd, 'upgrade packages' );
		if ( 0 == $check_upgrade ) {
			EE::success( 'Cache upgraded successfully.' );
		} else {
			EE::success( 'Oops Something went wrong!!' );
		}
	}

	/**
	 *  Similar to `apt-get upgrade`
	 */
	public static function dist_upgrade() {
		$dist_upgrade_cmd = 'DEBIAN_FRONTEND=noninteractive '.
                            'apt-get dist-upgrade -o '.
                            'Dpkg::Options::="--force-confdef"'.
                            ' -o '.
                            'Dpkg::Options::="--force-confold"'.
                            ' -y ';
		$dist_upgrade = EE::exec_cmd( $dist_upgrade_cmd, 'dist upgrade' );
		if ( 0 == $dist_upgrade ) {
			EE::success( 'Cache updated successfully.' );
		} else {
			EE::success( 'Oops Something went wrong!!' );
		}
	}

	/**
	 * @param $packages
	 */
	public static function install($packages) {
		if ( is_array( $packages ) ) {
			$all_packages = implode( ' ', $packages );
		} else {
			$all_packages = $packages;
		}
		$install_package_cmd = 'sudo DEBIAN_FRONTEND=noninteractive ' .
			                     'apt-get install -o ' .
			                     'Dpkg::Options::="--force-confdef"' .
			                     ' -o ' .
			                     'Dpkg::Options::="--force-confold"' .
			                     ' -y --allow-unauthenticated ' .
			                     $all_packages;
		$install_package = EE::exec_cmd( $install_package_cmd, 'installing package' );
		if ( 0 == $install_package ) {
			EE::success( 'Cache upgraded successfully.' );
		} else {
			EE::success( 'Oops Something went wrong!!' );
		}
	}

	/**
	 * @param      $packages
	 * @param bool $purge
	 */
	public static function remove($packages, $purge = false) {
		if ( is_array( $packages ) ) {
			$all_packages = implode( ' ', $packages );
		} else {
			$all_packages = $packages;
		}
		if ( $purge ) {
			$check_upgrade_cmd = 'sudo apt-get purge -y ' . $all_packages;
		} else {
			$check_upgrade_cmd = 'sudo apt-get remove -y ' . $all_packages;
		}
		$check_upgrade = EE::exec_cmd( $check_upgrade_cmd, 'removing packages' );
		if ( 0 == $check_upgrade ) {
			EE::success( 'Cache upgraded successfully.' );
		} else {
			EE::error( 'Oops Something went wrong!!' );
		}
	}

	/**
	 * Similar to `apt-get install --download-only PACKAGE_NAME`
	 *
	 * @param        $packages
	 * @param string $repo_url
	 * @param string $repo_key
	 */
	public static function download_only( $packages, $repo_url = '', $repo_key = '' ) {

		if ( is_array( $packages ) ) {
			$all_packages = implode( ' ', $packages );
		} else {
			$all_packages = $packages;
		}

		if ( ! empty( $repo_url ) ) {
			EE_REPO::add($repo_url);
		}

		if ( ! empty( $repo_key ) ) {
			EE_REPO::add_key($repo_key);
		}

		$download_packages_cmd = 'apt-get update && DEBIAN_FRONTEND=noninteractive '.
                                        'apt-get install -o '.
                                        'Dpkg::Options::="--force-confdef"'.
                                        ' -o '.
                                        'Dpkg::Options::="--force-confold"'.
                                        ' -y  --download-only ' . $all_packages;

		$download_packages = EE::exec_cmd( $download_packages_cmd, 'downloading packages...' );
		if ( 0 == $download_packages ) {
			EE::success( 'Packages downloaded successfully.' );
		} else {
			EE::error( 'Error in fetching dpkg package.\nReverting changes ..', false );
			if ( !empty($repo_url)) {
				EE_REPO::remove( $repo_url );
			}
		}
	}
}