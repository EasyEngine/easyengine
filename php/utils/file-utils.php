<?php
/**
 * Symfony filesystem functions.
 */
use Symfony\Component\Filesystem\Filesystem;

/**
 * Copy files.
 *
 * @param string $originFile
 * @param string $targetFile
 * @param bool $overwriteNewerFiles
 */
function ee_file_copy( $originFile, $targetFile, $overwriteNewerFiles = false ) {
	$filesystem = new Filesystem();
	$filesystem->copy( $originFile, $targetFile, $overwriteNewerFiles );
}

/**
 * Create directory.
 *
 * @param string|array|\Traversable $dirs
 * @param int $mode
 */
function ee_file_mkdir( $dirs, $mode = 0755 ) {
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
 * @param int $umask
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

function grep_string( $file, $string ) {
	$file_content = file_get_contents( $file );
	$lines        = explode( "\n", $file_content );

	foreach ( $lines as $num => $line ) {
		$pos = strpos( $line, $string );
		if ( $pos !== false ) {
			return true;
		}
	}

	return false;
}
