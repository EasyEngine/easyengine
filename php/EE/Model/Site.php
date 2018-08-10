<?php

namespace EE\Model;

/**
 * Site model class.
 */
class Site extends Base {

	protected static $table = 'sites';
	protected static $primary_key = 'site_url';

	/**
	 * Check if a site entry exists in the database.
	 *
	 * @param string $site_name Name of the site to be checked.
	 *
	 * @throws Exception
	 *
	 * @return bool Success.
	 */
	public static function site_in_db( $site_name ) {
		$db = new EE_DB();
		$site = $db->table( 'sites' )
			->select( 'id' )
			->where( 'site_name', $site_name )
			->first();

		if ( $site ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if a site entry exists in the database as well as if it is enbaled.
	 *
	 * @param String $site_name Name of the site to be checked.
	 *
	 * @throws Exception
	 *
	 * @return bool true  if site is enabled,
	 *              false if disabled or site does not exists.
	 */
	public static function site_enabled( $site_name ) {

		$db = new EE_DB();
		$site = $db->table( 'sites' )
			->select( 'id', 'site_enabled' )
			->where( 'site_name', $site_name )
			->first();

		if ( $site ) {
			return $site['site_enabled'];
		}

		return false;
	}

	/**
	 * Get site type.
	 *
	 * @param String $site_name Name of the site.
	 *
	 * @throws Exception
	 *
	 * @return string type of site.
	 */
	public static function get_site_command( $site_name ) {
		$db = new EE_DB();
		$site = $db->table( 'sites' )
			->select( 'site_command' )
			->where( 'site_name', $site_name )
			->first();

		return $site['site_command'];
	}

}
