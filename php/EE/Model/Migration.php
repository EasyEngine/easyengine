<?php

namespace EE\Model;

use EE_DB;

/**
 * Migration model class.
 */
class Migration extends Base {

	protected static $table = 'migrations';

	/**
	 * Returns all migrations from table.
	 */
	public static function get_migrations() {

		$db = new EE_DB();
		$migrations = $db->table( 'migrations' )
			->select( 'migration' )
			->get();

		return array_column( $migrations, 'migration' );
	}

}
