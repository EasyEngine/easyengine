<?php

namespace EE\Dispatcher;

use EE;

/**
 * Adds a command namespace without actual functionality.
 *
 * This is meant to provide the means to attach meta information to a namespace
 * when there's no actual command needed.
 *
 * In case a real command gets registered for the same name, it replaces the
 * command namespace.
 *
 * @package EE
 */
class CommandNamespace extends CompositeCommand {

	/**
	 * Show the usage for all subcommands contained
	 * by the composite command.
	 */
	public function show_usage() {
		$methods = $this->get_subcommands();

		$i = 0;
		$count = 0;

		foreach ( $methods as $name => $subcommand ) {
			$prefix = ( 0 == $i++ ) ? 'usage: ' : '   or: ';

			if ( \EE::get_runner()->is_command_disabled( $subcommand ) ) {
				continue;
			}

			\EE::line( $subcommand->get_usage( $prefix ) );
			$count++;
		}

		$cmd_name = implode( ' ', array_slice( get_path( $this ), 1 ) );
		$message = $count > 0
			? "See 'ee help $cmd_name <command>' for more information on a specific command."
			: "The namespace $cmd_name does not contain any usable commands in the current context.";

		\EE::line();
		\EE::line( $message );

	}
}
