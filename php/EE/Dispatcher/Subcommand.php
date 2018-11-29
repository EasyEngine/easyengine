<?php

namespace EE\Dispatcher;

use EE;
use EE\Utils;

/**
 * A leaf node in the command tree.
 *
 * @package EE
 */
class Subcommand extends CompositeCommand {

	private $alias;

	private $when_invoked;

	public function __construct( $parent, $name, $docparser, $when_invoked ) {
		parent::__construct( $parent, $name, $docparser );

		$this->when_invoked = $when_invoked;

		$this->alias = $docparser->get_tag( 'alias' );

		$this->synopsis = $docparser->get_synopsis();
		if ( ! $this->synopsis && $this->longdesc ) {
			$this->synopsis = self::extract_synopsis( $this->longdesc );
		}
	}

	/**
	 * Extract the synopsis from PHPdoc string.
	 *
	 * @param string $longdesc Command docs via PHPdoc
	 * @return string
	 */
	private static function extract_synopsis( $longdesc ) {
		preg_match_all( '/(.+?)[\r\n]+:/', $longdesc, $matches );
		return implode( ' ', $matches[1] );
	}

	/**
	 * Subcommands can't have subcommands because they
	 * represent code to be executed.
	 *
	 * @return bool
	 */
	public function can_have_subcommands() {
		return false;
	}

	/**
	 * Get the synopsis string for this subcommand.
	 * A synopsis defines what runtime arguments are
	 * expected, useful to humans and argument validation.
	 *
	 * @return string
	 */
	public function get_synopsis() {
		return $this->synopsis;
	}

	/**
	 * Set the synopsis string for this subcommand.
	 *
	 * @param string
	 */
	public function set_synopsis( $synopsis ) {
		$this->synopsis = $synopsis;
	}

	/**
	 * If an alias is set, grant access to it.
	 * Aliases permit subcommands to be instantiated
	 * with a secondary identity.
	 *
	 * @return string
	 */
	public function get_alias() {
		return $this->alias;
	}

	/**
	 * Print the usage details to the end user.
	 *
	 * @param string $prefix
	 */
	public function show_usage( $prefix = 'usage: ' ) {
		\EE::line( $this->get_usage( $prefix ) );
	}

	/**
	 * Get the usage of the subcommand as a formatted string.
	 *
	 * @param string $prefix
	 * @return string
	 */
	public function get_usage( $prefix ) {
		return sprintf(
			'%s%s %s',
			$prefix,
			implode( ' ', get_path( $this ) ),
			$this->get_synopsis()
		);
	}

	/**
	 * Validate the supplied arguments to the command.
	 * Throws warnings or errors if arguments are missing
	 * or invalid.
	 *
	 * @param array $args
	 * @param array $assoc_args
	 * @param array $extra_args
	 * @return array list of invalid $assoc_args keys to unset
	 */
	private function validate_args( $args, $assoc_args, $extra_args ) {
		$synopsis = $this->get_synopsis();
		if ( ! $synopsis ) {
			return array( array(), $args, $assoc_args, $extra_args );
		}

		$validator = new \EE\SynopsisValidator( $synopsis );

		$cmd_path = implode( ' ', get_path( $this ) );
		foreach ( $validator->get_unknown() as $token ) {
			\EE::warning(
				sprintf(
					'The `%s` command has an invalid synopsis part: %s',
					$cmd_path,
					$token
				)
			);
		}

		if ( ! $validator->enough_positionals( $args ) ) {
			$this->show_usage();
			exit( 1 );
		}

		$unknown_positionals = $validator->unknown_positionals( $args );
		if ( ! empty( $unknown_positionals ) && 'wp' !== $this->name ) {
			\EE::error(
				'Too many positional arguments: ' .
				implode( ' ', $unknown_positionals )
			);
		}

		$synopsis_spec = \EE\SynopsisParser::parse( $synopsis );
		$i = 0;
		$errors = array(
			'fatal' => array(),
			'warning' => array(),
		);
		$mock_doc = array( $this->get_shortdesc(), '' );
		$mock_doc = array_merge( $mock_doc, explode( "\n", $this->get_longdesc() ) );
		$mock_doc = '/**' . PHP_EOL . '* ' . implode( PHP_EOL . '* ', $mock_doc ) . PHP_EOL . '*/';
		$docparser = new \EE\DocParser( $mock_doc );
		foreach ( $synopsis_spec as $spec ) {
			if ( 'positional' === $spec['type'] ) {
				$spec_args = $docparser->get_arg_args( $spec['name'] );
				if ( ! isset( $args[ $i ] ) ) {
					if ( isset( $spec_args['default'] ) ) {
						$args[ $i ] = $spec_args['default'];
					}
				}
				if ( isset( $spec_args['options'] ) ) {
					if ( ! empty( $spec['repeating'] ) ) {
						do {
							if ( isset( $args[ $i ] ) && ! in_array( $args[ $i ], $spec_args['options'] ) ) {
								\EE::error( 'Invalid value specified for positional arg.' );
							}
							$i++;
						} while ( isset( $args[ $i ] ) );
					} else {
						if ( isset( $args[ $i ] ) && ! in_array( $args[ $i ], $spec_args['options'] ) ) {
							\EE::error( 'Invalid value specified for positional arg.' );
						}
					}
				}
				$i++;
			} elseif ( 'assoc' === $spec['type'] ) {
				$spec_args = $docparser->get_param_args( $spec['name'] );
				if ( ! isset( $assoc_args[ $spec['name'] ] ) && ! isset( $extra_args[ $spec['name'] ] ) ) {
					if ( isset( $spec_args['default'] ) ) {
						$assoc_args[ $spec['name'] ] = $spec_args['default'];
					}
				}
				if ( isset( $assoc_args[ $spec['name'] ] ) && isset( $spec_args['options'] ) ) {
					if ( ! in_array( $assoc_args[ $spec['name'] ], $spec_args['options'] ) ) {
						$errors['fatal'][ $spec['name'] ] = "Invalid value specified for '{$spec['name']}'";
					}
				}
			}
		}

		list( $returned_errors, $to_unset ) = $validator->validate_assoc(
			array_merge( \EE::get_config(), $extra_args, $assoc_args )
		);
		foreach ( array( 'fatal', 'warning' ) as $error_type ) {
			$errors[ $error_type ] = array_merge( $errors[ $error_type ], $returned_errors[ $error_type ] );
		}

		if ( 'help' !== $this->name ) {
			foreach ( $validator->unknown_assoc( $assoc_args ) as $key ) {
				$suggestion = Utils\get_suggestion(
					$key,
					$this->get_parameters( $synopsis_spec ),
					$threshold = 2
				);

				$errors['fatal'][] = sprintf(
					'unknown --%s parameter%s',
					$key,
					! empty( $suggestion ) ? PHP_EOL . "Did you mean '--{$suggestion}'?" : ''
				);
			}
		}

		if ( ! empty( $errors['fatal'] ) && 'wp' !== $this->name ) {
			$out = 'Parameter errors:';
			foreach ( $errors['fatal'] as $key => $error ) {
				$out .= "\n {$error}";
				if ( $desc = $docparser->get_param_desc( $key ) ) {
					$out .= " ({$desc})";
				}
			}

			\EE::error( $out );
		}

		array_map( '\\EE::warning', $errors['warning'] );

		return array( $to_unset, $args, $assoc_args, $extra_args );
	}

	/**
	 * Invoke the subcommand with the supplied arguments.
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function invoke( $args, $assoc_args, $extra_args ) {

		$extra_positionals = array();
		foreach ( $extra_args as $k => $v ) {
			if ( is_numeric( $k ) ) {
				if ( ! isset( $args[ $k ] ) ) {
					$extra_positionals[ $k ] = $v;
				}
				unset( $extra_args[ $k ] );
			}
		}
		$args += $extra_positionals;

		list( $to_unset, $args, $assoc_args, $extra_args ) = $this->validate_args( $args, $assoc_args, $extra_args );

		foreach ( $to_unset as $key ) {
			unset( $assoc_args[ $key ] );
		}

		$path   = get_path( $this->get_parent() );
		$parent = implode( ' ', array_slice( $path, 1 ) );
		$cmd    = $this->name;
		if ( $parent ) {
			EE::do_hook( "before_invoke:{$parent}", $args, $assoc_args );
			$cmd = $parent . ' ' . $cmd;
		}
		EE::do_hook( "before_invoke:{$cmd}", $args, $assoc_args );

		call_user_func( $this->when_invoked, $args, array_merge( $extra_args, $assoc_args ) );

		if ( $parent ) {
			EE::do_hook( "after_invoke:{$parent}" );
		}
		EE::do_hook( "after_invoke:{$cmd}" );
	}

	/**
	 * Get an array of parameter names, by merging the command-specific and the
	 * global parameters.
	 *
	 * @param array  $spec Optional. Specification of the current command.
	 *
	 * @return array Array of parameter names
	 */
	private function get_parameters( $spec = array() ) {
		$local_parameters = array_column( $spec, 'name' );
		$global_parameters = array_column(
			EE\SynopsisParser::parse( $this->get_global_params() ),
			'name'
		);

		return array_unique( array_merge( $local_parameters, $global_parameters ) );
	}
}

