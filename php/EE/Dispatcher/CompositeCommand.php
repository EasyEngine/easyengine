<?php

namespace EE\Dispatcher;

use \EE\Utils;

/**
 * A non-leaf node in the command tree.
 * Contains one or more Subcommands.
 *
 * @package EE
 */
class CompositeCommand {

	protected $name, $shortdesc, $synopsis, $docparser;

	protected $parent, $subcommands = array();

	/**
	 * Instantiate a new CompositeCommand
	 *
	 * @param mixed $parent Parent command (either Root or Composite)
	 * @param string $name Represents how command should be invoked
	 * @param \EE\DocParser
	 */
	public function __construct( $parent, $name, $docparser ) {
		$this->parent = $parent;

		$this->name = $name;

		$this->shortdesc = $docparser->get_shortdesc();
		$this->longdesc = $docparser->get_longdesc();
		$this->longdesc .= $this->get_global_params();
		$this->docparser = $docparser;

		$when_to_invoke = $docparser->get_tag( 'when' );
		if ( $when_to_invoke ) {
			\EE::get_runner()->register_early_invoke( $when_to_invoke, $this );
		}
	}

	/**
	 * Get the parent composite (or root) command
	 *
	 * @return mixed
	 */
	public function get_parent() {
		return $this->parent;
	}

	/**
	 * Add a named subcommand to this composite command's
	 * set of contained subcommands.
	 *
	 * @param string $name Represents how subcommand should be invoked
	 * @param \EE\Dispatcher\Subcommand
	 */
	public function add_subcommand( $name, $command ) {
		$this->subcommands[ $name ] = $command;
	}

	/**
	 * Remove a named subcommand from this composite command's set of contained
	 * subcommands
	 *
	 * @param string $name Represents how subcommand should be invoked
	 */
	public function remove_subcommand( $name ) {
		if ( isset( $this->subcommands[ $name ] ) ) {
			unset( $this->subcommands[ $name ] );
		}
	}


	/**
	 * Composite commands always contain subcommands.
	 *
	 * @return true
	 */
	public function can_have_subcommands() {
		return true;
	}

	/**
	 * Get the subcommands contained by this composite
	 * command.
	 *
	 * @return array
	 */
	public function get_subcommands() {
		ksort( $this->subcommands );

		return $this->subcommands;
	}

	/**
	 * Get the name of this composite command.
	 *
	 * @return string
	 */
	public function get_name() {
		return $this->name;
	}

	/**
	 * Get the short description for this composite
	 * command.
	 *
	 * @return string
	 */
	public function get_shortdesc() {
		return $this->shortdesc;
	}

	/**
	 * Set the short description for this composite command.
	 *
	 * @param string
	 */
	public function set_shortdesc( $shortdesc ) {
		$this->shortdesc = $shortdesc;
	}

	/**
	 * Get the long description for this composite
	 * command.
	 *
	 * @return string
	 */
	public function get_longdesc() {
		return $this->longdesc . $this->get_global_params();
	}

	/**
	 * Set the long description for this composite command
	 *
	 * @param string
	 */
	public function set_longdesc( $longdesc ) {
		$this->longdesc = $longdesc;
	}

	/**
	 * Get the synopsis for this composite command.
	 * As a collection of subcommands, the composite
	 * command is only intended to invoke those
	 * subcommands.
	 *
	 * @return string
	 */
	public function get_synopsis() {
		return '<command>';
	}

	/**
	 * Get the usage for this composite command.
	 *
	 * @return string
	 */
	public function get_usage( $prefix ) {
		return sprintf( "%s%s %s",
			$prefix,
			implode( ' ', get_path( $this ) ),
			$this->get_synopsis()
		);
	}

	/**
	 * Show the usage for all subcommands contained
	 * by the composite command.
	 */
	public function show_usage() {
		$methods = $this->get_subcommands();

		$i = 0;

		foreach ( $methods as $name => $subcommand ) {
			$prefix = ( 0 == $i++ ) ? 'usage: ' : '   or: ';

			if ( \EE::get_runner()->is_command_disabled( $subcommand ) ) {
				continue;
			}

			\EE::line( $subcommand->get_usage( $prefix ) );
		}

		$cmd_name = implode( ' ', array_slice( get_path( $this ), 1 ) );

		\EE::line();
		\EE::line( "See 'ee help $cmd_name <command>' for more information on a specific command." );
	}

	/**
	 * When a composite command is invoked, it shows usage
	 * docs for its subcommands.
	 *
	 * @param array $args
	 * @param array $assoc_args
	 * @param array $extra_args
	 */
	public function invoke( $args, $assoc_args, $extra_args ) {
		$this->show_usage();
	}

	/**
	 * Given supplied arguments, find a contained
	 * subcommand
	 *
	 * @param array $args
	 * @return \EE\Dispatcher\Subcommand|false
	 */
	public function find_subcommand( &$args ) {
		$name = array_shift( $args );

		$subcommands = $this->get_subcommands();

		if ( !isset( $subcommands[ $name ] ) ) {
			$aliases = self::get_aliases( $subcommands );

			if ( isset( $aliases[ $name ] ) ) {
				$name = $aliases[ $name ];
			}
		}

		if ( !isset( $subcommands[ $name ] ) )
			return false;

		return $subcommands[ $name ];
	}

	/**
	 * Get any registered aliases for this composite command's
	 * subcommands.
	 *
	 * @param array $subcommands
	 * @return array
	 */
	private static function get_aliases( $subcommands ) {
		$aliases = array();

		foreach ( $subcommands as $name => $subcommand ) {
			$alias = $subcommand->get_alias();
			if ( $alias )
				$aliases[ $alias ] = $name;
		}

		return $aliases;
	}

	/**
	 * Composite commands can only be known by one name.
	 *
	 * @return false
	 */
	public function get_alias() {
		return false;
	}

	/***
	 * Get the list of global parameters
	 *
	 * @param string $root_command whether to include or not root command specific description
	 * @return string
	 */
	protected function get_global_params( $root_command = false ) {
		$binding = array();
		$binding['root_command'] = $root_command;

		if (! $this->can_have_subcommands() || ( is_object( $this->parent ) && get_class( $this->parent ) == 'EE\Dispatcher\CompositeCommand' )) {
			$binding['is_subcommand'] = true;
		}

		foreach ( \EE::get_configurator()->get_spec() as $key => $details ) {
			if ( false === $details['runtime'] )
				continue;

			if ( isset( $details['deprecated'] ) )
				continue;

			if ( isset( $details['hidden'] ) )
				continue;

			if ( true === $details['runtime'] )
				$synopsis = "--[no-]$key";
			else
				$synopsis = "--$key" . $details['runtime'];

			$binding['parameters'][] = array(
				'synopsis' => $synopsis,
				'desc' => $details['desc'],
			);
		}

		if ( $this->get_subcommands() ) {
			$binding['has_subcommands'] = true;
		}

		return Utils\mustache_render( 'man-params.mustache', $binding );
	}
}

