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
		'sitename'     => '',
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


	if ( empty( $data['sitename'] ) ) {
		EE::debug( "Sitename is empty." );

		return false;
	}
	$is_site_exist = is_site_exist( $data['sitename'] );

	if ( $is_site_exist ) {
		EE::error( "Site is already exist." );

		return false;
	}

	$insert_site_data = EE_Sqlite_Db::insert( $data );

	return $insert_site_data;
}

function update_site( $data, $where ) {

	$update_site_data = EE_Sqlite_Db::update( $data, $where );

	return $update_site_data;
}

function delete_site( $where ) {
	$delete_site = EE_Sqlite_Db::delete( $where );

	return $delete_site;
}

function get_all_sites() {
	$site_info = EE_Sqlite_Db::select();

	return $site_info;
}

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

function site_info( $site_name ) {
	$where     = array(
		'sitename' => $site_name,
	);
	$site_info = EE_Sqlite_Db::select( $where );

	return $site_info;
}