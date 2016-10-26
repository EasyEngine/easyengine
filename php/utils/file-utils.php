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
 * @param int                       $mode
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
 * Rename/Move file.
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
 * Append content in file.
 *
 * @param $file_path
 * @param $content
 */
function ee_file_append_content( $file_path, $content ) {
	$file = fopen( $file_path, 'a' );
	fwrite( $file, $content . "\n" );
	fclose( $file );
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
 * @param $linkfile
 */
function ee_file_unlink( $linkfile ) {

	if ( ee_file_exists( $linkfile ) ) {
		if ( is_link( $linkfile ) ) {
			unlink( $linkfile );
		} else {
			EE::log( "{$linkfile} exists but not symbolic link\n" );
		}
	}

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
 * Search and replace content in file.
 *
 * @param $file
 * @param $search
 * @param $replace
 */
function ee_file_search_replace( $file, $search, $replace ) {
	$file_content = file_get_contents( $file );
	file_put_contents( $file, str_replace( $search, $replace, $file_content ) );
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

/**
 * Check if string is exist or not in file.
 * @param $file
 * @param $string
 *
 * @return bool
 */
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

/**
 * Get data from ee config file.
 *
 * @param        $section
 * @param string $key
 *
 * @return mixed|string
 */
function get_ee_config( $section, $key = '' ) {
	$config_data = get_config_data( EE_CONFIG_FILE, $section, $key );

	return $config_data;
}

/**
 * Get data from mysql config file.
 *
 * @param        $section
 * @param string $key
 *
 * @return mixed|string
 */
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

/**
 * Get data from gitconfig file.
 *
 * @param        $section
 * @param string $key
 *
 * @return mixed|string
 */
function get_ee_git_config( $section, $key = '' ) {
	try {
		$git_config_file = "~/.gitconfig";
		$config_data     = get_config_data( $git_config_file, $section, $key );

		return $config_data;
	} catch ( Exception $e ) {
		$user_name                   = EE::input_value( "Enter your name: " );
		$user_email                  = EE::input_value( "Enter your email: " );
		$config_data['user']['name'] = $user_name;
		$config_data['user']['name'] = $user_email;
		$set_username                = EE::exec_cmd( "git config --global user.name {$user_name}" );
		$set_useremail               = EE::exec_cmd( "git config --global user.email {$user_email}" );

		if ( ! empty( $key ) ) {
			return empty( $config_data[ $section ][ $key ] ) ? '' : $config_data[ $section ][ $key ];
		} else {
			return empty( $config_data[ $section ] ) ? '' : $config_data[ $section ];
		}
	}
}

/**
 * Read data from config file using config-parser.
 *
 * @param        $config_file
 * @param        $section
 * @param string $key
 *
 * @return mixed|string
 */
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

/**
 * Write data in config file using config-parser.
 * @param      $config_file
 * @param      $data
 * @param bool $new_section
 */
function set_config_data( $config_file, $data, $new_section = false ) {
	$ee_config = new ConfigParser();
	$ee_config->read( $config_file );
	foreach ( $data as $section ) {
		if ( $new_section && ! $ee_config->hasSection( $section ) ) {
			$ee_config->addSection( $section );
		}
		if ( $ee_config->hasSection( $section ) ) {
			foreach ( $section as $key => $value ) {
				$ee_config->set( $section, $key, $value );
			}
		}
	}
	$ee_config->save();
}