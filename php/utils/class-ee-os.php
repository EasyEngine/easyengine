<?php

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;

class EE_OS {

	/**
	 * Return Platform codename. i.e xenial
	 * @return string
	 */
	public static function ee_platform_codename() {
		$os_codename = EE::exec_cmd_output( "lsb_release -sc | tr -d '\n'" );

		return strtolower( $os_codename );
	}

	/**
	 * Return distribution of the os. i.e Ubuntu
	 * @return string
	 */
	public static function ee_platform_distro() {
		$os_distro = EE::exec_cmd_output( "lsb_release -si | tr -d '\n'" );

		return strtolower( $os_distro );
	}

	/**
	 * Retrun OS version. i.e. 16.04
	 * @return string
	 */
	public static function ee_platform_version() {
		$os_version = EE::exec_cmd_output( "lsb_release -sr | tr -d '\n''" );

		return $os_version;
	}

	/**
	 * Return EasyEngine version.
	 *
	 * @return string
	 */
	public static function ee_core_version() {
		$ee_version = EE_VERSION;

		return $ee_version;
	}

	/**
	 * Return platform architecture. i.e. x86_64
	 * @return string
	 */
	public static function ee_platform_architecture() {
		$platform_architecture = EE::exec_cmd_output( "uname --hardware-platform  | tr -d '\n'" );
		return $platform_architecture;
	}

	/**
	 * Add swap memory.
	 */
	public static function add_swap() {
		// TODO:
	}


	/**
	 * Get system memory info by reading '/proc/meminfo' file.
	 *
	 * @return array
	 */
	public static function get_system_mem_info() {
		/*
 		   Array
				(
					[MemTotal] =>
					[MemFree] =>
					[SwapCached] =>
					[SwapTotal] => 4099.068
					[SwapFree] => 4089.52
					...
				)
 		*/
		$data = explode("\n", file_get_contents("/proc/meminfo"));
		$meminfo = array();
		foreach ($data as $line) {
			list($key, $val) = explode(":", $line);
			$val = explode(' ', trim($val));
			$val = $val[0] * 0.001;
			$meminfo[$key] = trim($val);
		}
		return $meminfo;
	}
}
