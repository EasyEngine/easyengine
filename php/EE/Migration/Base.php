<?php

namespace EE\Migration;

use Symfony\Component\Filesystem\Filesystem;

abstract class Base {

	public $status = 'incomplete';
	protected $is_first_execution;
	protected $skip_this_migration;
	protected $backup_dir;
	protected $backup_file;
	protected $fs;

	public function __construct() {
		$this->fs                  = new Filesystem();
		$this->skip_this_migration = false;
		$this->is_first_execution  = ! \EE\Model\Option::get( 'version' );
		$this->backup_dir          = EE_BACKUP_DIR;
		$this->fs->mkdir( $this->backup_dir );
	}

	abstract public function up();

	abstract public function down();
}
