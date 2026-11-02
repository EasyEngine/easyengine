<?php

namespace EE\Migration;

/**
 * Caps host syslog growth (maxsize on the rsyslog logrotate rule, hourly logrotate), so a postfix logging loop
 * through /dev/log can't fill the disk.
 *
 * Runs once: these are host files, so a later change by the admin is kept.
 */
class CapHostSyslog extends Base {

	public function up() {
		\EE\Logrotate\Utils::setup_host_log_cap();
	}

	/**
	 * Nothing to undo: up() never fails and is idempotent when the migration is retried.
	 */
	public function down() {
	}
}
