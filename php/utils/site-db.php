<?php

/**
 * Add New Site record information into ee database.
 *
 * @param $data
 *
 * @return bool
 */
function add_new_site( $data ) {

	$default_args = array(
		'site_name'     => '',
		'site_type'    => '',
		'cache_type'   => '',
		'site_path'    => '',
		'created_on'   => date( "Y-m-d H:i:s" ),
		'is_enabled'   => 1,
		'is_ssl'       => 0,
		'storage_fs'   => 'ext4',
		'storage_db'   => 'mysql',
		'db_name'      => 'None',
		'db_user'      => 'None',
		'db_password'  => 'None',
		'db_host'      => 'localhost',
		'is_hhvm'      => 0,
		'is_pagespeed' => 0,
		'php_version'  => '5.6',
	);

	$data = array_merge( $default_args, $data );


	if ( empty( $data['site_name'] ) ) {
		EE::debug( "Sitename is empty." );

		return false;
	}
	$is_site_exist = is_site_exist( $data['site_name'] );

	if ( $is_site_exist ) {
		EE::error( "Site is already exist." );

		return false;
	}

	$insert_site_data = EE_Sqlite_Db::insert( $data );

	return $insert_site_data;
}

/**
 * Update site info in sqlite ee.db.
 *
 * @param $data
 * @param $where
 *
 * @return bool
 */
function update_site( $data, $where ) {

	$update_site_data = EE_Sqlite_Db::update( $data, $where );

	return $update_site_data;
}

/**
 * Delete site from sqlite ee.db
 * @param $where
 *
 * @return bool
 */
function delete_site( $where ) {
	$delete_site = EE_Sqlite_Db::delete( $where );

	return $delete_site;
}

/**
 * Get all site info from sqlite ee.db.
 *
 * @return array|bool
 */
function get_all_sites() {
	$site_info = EE_Sqlite_Db::select();

	return $site_info;
}

/**
 * Check if site is exist or not.
 *
 * @param $site_name
 *
 * @return bool
 */
function is_site_exist( $site_name ) {
	$where = array(
		'sitename' => $site_name,
	);

	$site_exist_id = EE_Sqlite_Db::select( $where );

	if ( $site_exist_id ) {
		return true;
	}

	return false;
}

/**
 * Get site info form site name.
 *
 * @param $site_name
 *
 * @return array|bool
 */
function get_site_info( $site_name ) {
	$where     = array(
		'sitename' => $site_name,
	);
	$site_info = EE_Sqlite_Db::select( $where );

	return empty( $site_info[0] ) ? array() : $site_info[0];
}