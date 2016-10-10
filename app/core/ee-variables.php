<?php

/**
 * Created by PhpStorm.
 * User: rahul
 * Date: 10/10/16
 * Time: 5:20 PM
 */
class EE_Variables {

	/**
	 * Intialization of core variables.
	 */

	// # EasyEngine version
	public static $ee_version = "3.7.4";

	// # Repo path
	public static $ee_repo_file = "ee-repo.list";

	public static function get_ee_repo_file_path() {
//		$ee_repo_file_path = "/etc/apt/sources.list.d/" . self::$ee_repo_file;
		$ee_repo_file_path = EE_CLI_ROOT . '/' . self::$ee_repo_file;
		return $ee_repo_file_path;
	}
}