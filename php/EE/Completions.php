<?php

namespace EE;

use EE;
use EE\Dispatcher\CommandFactory;
use EE\Model\Site;

class Completions {

	private $words;
	private $opts = array();

	public function __construct( $line, $shell ) {
		// TODO: properly parse single and double quotes
		$this->words = explode( ' ', $line );

		// first word is always `ee`
		array_shift( $this->words );

		// last word is either empty or an incomplete subcommand
		$this->cur_word = end( $this->words );
		if ( '' !== $this->cur_word && ! preg_match( '/^\-/', $this->cur_word ) ) {
			array_pop( $this->words );
		}

		$is_alias = false;
		$is_help  = false;
		if ( ! empty( $this->words[0] ) && preg_match( '/^@/', $this->words[0] ) ) {
			array_shift( $this->words );
			// `ee @al` is false, but `ee @all ` is true.
			if ( count( $this->words ) ) {
				$is_alias = true;
			}
		} elseif ( ! empty( $this->words[0] ) && 'help' === $this->words[0] ) {
			array_shift( $this->words );
			$is_help = true;
		}

		$r = $this->get_command( $this->words );
		if ( ! is_array( $r ) ) {
			return;
		}

		list( $command, $args, $assoc_args ) = $r;

		$spec = SynopsisParser::parse( $command->get_synopsis() );

		foreach ( $spec as $arg ) {
			if ( 'positional' === $arg['type'] && 'file' === $arg['name'] ) {
				$this->add( '<file> ' );
				return;
			}
		}
		if ( $command->can_have_subcommands() ) {
			// add completion when command is `ee` and alias isn't set.
			if ( 'ee' === $command->get_name() && false === $is_alias && false === $is_help ) {
				$aliases = \EE::get_configurator()->get_aliases();
				foreach ( $aliases as $name => $_ ) {
					$this->add( "$name " );
				}
			}
			foreach ( $command->get_subcommands() as $name => $subcommand ) {
				if ( $shell === 'zsh') {
					$this->add( $name . ':' . $subcommand->get_shortdesc() );
				} else {
					$this->add( $name );
				}
			}
		} else {
			foreach ( $spec as $arg ) {
				if ( in_array( $arg['type'], array( 'flag', 'assoc' ), true ) ) {
					if ( isset( $assoc_args[ $arg['name'] ] ) ) {
						continue;
					}

					$opt = "--{$arg['name']}";

					if ( 'flag' === $arg['type'] ) {
						$opt .= ' ';
					} elseif ( ! $arg['value']['optional'] ) {
						$opt .= '=';
					}

					$this->add( $opt );
				}
			}
		}
	}

	private function get_command( $words ) {
		$positional_args = $assoc_args = array();

		foreach ( $words as $arg ) {
			if ( preg_match( '|^--([^=]+)=?|', $arg, $matches ) ) {
				$assoc_args[ $matches[1] ] = true;
			} else {
				$positional_args[] = $arg;
			}
		}

		$this->maybe_add_site_command( $words );

		$r = \EE::get_runner()->find_command_to_run( $positional_args );
		if ( ! is_array( $r ) && array_pop( $positional_args ) === $this->cur_word ) {
			$r = \EE::get_runner()->find_command_to_run( $positional_args );
		}

		if ( ! is_array( $r ) ) {
			return $r;
		}

		list( $command, $args ) = $r;

		return array( $command, $args, $assoc_args );
	}

	/**
	 * Adds correct site-type to EE runner if autocompletion of site command is required
	 */
	private function maybe_add_site_command( array $words ) {
		if ( count( $words ) > 0 && 'site' === $words[0] ) {
			$type       = $this->get_site_type( $words, 'html' );
			$site_types = \Site_Command::get_site_types();

			$command      = EE::get_root_command();
			$callback     = $site_types[ $type ];
			$leaf_command = CommandFactory::create( 'site', $callback, $command );
			$command->add_subcommand( 'site', $leaf_command );
		}
	}

	/**
	 * Returns correct site-type for completion. Only for `site create`, type is specified in command.
	 * For other commands, it is fetched from EE db.comp
	 */
	private function get_site_type( $words, $default_site_type ) {
		$type = $default_site_type;

		foreach ( $words as $arg ) {
			if ( preg_match( '|^--type=(\S+)|', $arg, $matches ) ) {
				$type = $matches[1];
			}
		}

		if ( count( $words ) >= 3 && 'create' === $words[1] && ! preg_match( '|^--|', $words[2] ) ) {
			$sitename = str_replace( array( 'https://', 'http://' ), '', $words[2] );
			$sitetype = Site::find( $sitename, array( 'site_type' ) );

			if ( $sitetype ) {
				$type = $sitetype->site_type;
			}
		}

		return $type;
	}

	private function add( $opt ) {
		if ( '' !== $this->cur_word ) {
			if ( 0 !== strpos( $opt, $this->cur_word ) ) {
				return;
			}
		}

		$this->opts[] = $opt;
	}

	public function render() {
		foreach ( $this->opts as $opt ) {
			\EE::line( $opt );
		}
	}
}
