<?php
/**
 * Symfony filesystem functions.
 */
use Symfony\Component\Filesystem\Filesystem;
use NoiseLabs\ToolKit\ConfigParser\ConfigParser;

/**
 * Copy files.
 *
 * @param string $originFile
 * @param string $targetFile
 * @param bool   $overwriteNewerFiles
 */
function ee_file_copy( $originFile, $targetFile, $overwriteNewerFiles = false ) {
	$filesystem = new Filesystem();
	$filesystem->copy( $originFile, $targetFile, $overwriteNewerFiles );
}

/**
 * Create directory.
 *
 * @param string|array|\Traversable $dirs
 * @param int                       $mode
 */
function ee_file_mkdir( $dirs, $mode = 0777 ) {
	$filesystem = new Filesystem();
	$filesystem->mkdir( $dirs, $mode );
}

/**
 * Check if file is already exist or not.
 *
 * @param string $files
 *
 * @return bool
 */
function ee_file_exists( $files ) {
	$filesystem = new Filesystem();
	$exist      = $filesystem->exists( $files );

	return $exist;
}

/**
 * Remove files.
 *
 * @param $files
 */
function ee_file_remove( $files ) {
	$filesystem = new Filesystem();
	$filesystem->remove( $files );
}

/**
 * Change files permission.
 *
 * @param      $files
 * @param      $mode
 * @param int  $umask
 * @param bool $recursive
 */
function ee_file_chmod( $files, $mode, $umask = 0000, $recursive = false ) {
	$filesystem = new Filesystem();
	$filesystem->chmod( $files, $mode, $umask, $recursive );
}

/**
 * Change user permission.
 *
 * @param      $files
 * @param      $user
 * @param bool $recursive
 */
function ee_file_chown( $files, $user, $recursive = false ) {
	$filesystem = new Filesystem();
	$filesystem->chown( $files, $user, $recursive );
}

/**
 * Change files groups.
 *
 * @param      $files
 * @param      $group
 * @param bool $recursive
 */
function ee_file_chgrp( $files, $group, $recursive = false ) {
	$filesystem = new Filesystem();
	$filesystem->chgrp( $files, $group, $recursive );
}

/**
 * Rename file.
 *
 * @param      $origin
 * @param      $target
 * @param bool $overwrite
 */
function ee_file_rename( $origin, $target, $overwrite = false ) {
	$filesystem = new Filesystem();
	$filesystem->rename( $origin, $target, $overwrite );
}

/**
 * Create symlink of directory.
 *
 * @param      $originDir
 * @param      $targetDir
 * @param bool $copyOnWindows
 */
function ee_file_symlink( $originDir, $targetDir, $copyOnWindows = false ) {
	$filesystem = new Filesystem();
	$filesystem->symlink( $originDir, $targetDir, $copyOnWindows );
}

/**
 * Make relative path.
 *
 * @param $endPath
 * @param $startPath
 */
function ee_file_make_path_relative( $endPath, $startPath ) {
	$filesystem = new Filesystem();
	$filesystem->makePathRelative( $endPath, $startPath );
}


/**
 * Check absolute path of file.
 *
 * @param $file
 *
 * @return bool
 */
function ee_file_is_absolute_path( $file ) {
	$filesystem    = new Filesystem();
	$absolute_path = $filesystem->isAbsolutePath( $file );

	return $absolute_path;
}

/**
 * Dump content in file.
 *
 * @param     $filename
 * @param     $content
 * @param int $mode
 */
function ee_file_dump( $filename, $content, $mode = 0666 ) {
	$filesystem = new Filesystem();
	$filesystem->dumpFile( $filename, $content, $mode );
}

function get_ee_config( $section, $key = '' ) {
	$config_data = get_config_data( EE_CONFIG_FILE, $section, $key );

	return $config_data;
}

function get_mysql_config( $section, $key = '' ) {
	$my_cnf_file = '';
	if ( ee_file_exists( '/etc/mysql/conf.d/my.cnf' ) ) {
		$my_cnf_file = '/etc/mysql/conf.d/my.cnf';
	} else if ( ee_file_exists( '~/.my.cnf' ) ) {
		$my_cnf_file = '~/.my.cnf';
	}
	if ( ! empty( $my_cnf_file ) ) {
		$config_data = get_config_data( $my_cnf_file, $section, $key );

		return $config_data;
	}

	return '';
}

function get_config_data( $config_file, $section, $key = '' ) {

	$ee_config = new ConfigParser();
	$ee_config->read( $config_file );
	$get_config_data = '';
	if ( ! empty( $ee_config[ $section ] ) ) {
		$get_config_data = $ee_config[ $section ];
		if ( ! empty( $key ) ) {
			if ( ! empty( $ee_config[ $section ][ $key ] ) ) {
				$get_config_data = $ee_config[ $section ][ $key ];
			} else {
				$get_config_data = '';
			}
		}
	}

	return $get_config_data;
}

function set_config_data() {
	//	$cfg->set('server', '192.168.1.1.');
}