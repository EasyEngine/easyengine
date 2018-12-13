<?php

namespace EE\Dispatcher;

use EE;
use ReflectionFunction;
use ReflectionClass;
use EE\DocParser;

/**
 * Creates CompositeCommand or Subcommand instances.
 *
 * @package EE
 */
class CommandFactory {

	// Cache of file contents, indexed by filename. Only used if opcache.save_comments is disabled.
	private static $file_contents = array();

	/**
	 * Create a new CompositeCommand (or Subcommand if class has __invoke())
	 *
	 * @param string $name Represents how the command should be invoked
	 * @param string $callable A subclass of EE_Command, a function, or a closure
	 * @param mixed $parent The new command's parent Composite (or Root) command
	 */
	public static function create( $name, $callable, $parent ) {

		if ( ( is_object( $callable ) && ( $callable instanceof \Closure ) )
			|| ( is_string( $callable ) && function_exists( $callable ) ) ) {
			$reflection = new ReflectionFunction( $callable );
			$command = self::create_subcommand( $parent, $name, $callable, $reflection );
		} elseif ( is_array( $callable ) && is_callable( $callable ) ) {
			$reflection = new ReflectionClass( $callable[0] );
			$command = self::create_subcommand(
				$parent, $name, array( $callable[0], $callable[1] ),
				$reflection->getMethod( $callable[1] )
			);
		} else {
			$reflection = new ReflectionClass( $callable );
			if ( $reflection->isSubclassOf( '\EE\Dispatcher\CommandNamespace' ) ) {
				$command = self::create_namespace( $parent, $name, $callable );
			} elseif ( $reflection->hasMethod( '__invoke' ) ) {
				$class = is_object( $callable ) ? $callable : $reflection->name;
				$command = self::create_subcommand(
					$parent, $name, array( $class, '__invoke' ),
					$reflection->getMethod( '__invoke' )
				);
			} else {
				$command = self::create_composite_command( $parent, $name, $callable );
			}
		}

		return $command;
	}

	/**
	 * Clear the file contents cache.
	 */
	public static function clear_file_contents_cache() {
		self::$file_contents = array();
	}

	/**
	 * Create a new Subcommand instance.
	 *
	 * @param mixed $parent The new command's parent Composite command
	 * @param string $name Represents how the command should be invoked
	 * @param mixed $callable A callable function or closure, or class name and method
	 * @param object $reflection Reflection instance, for doc parsing
	 * @param string $class A subclass of EE_Command
	 * @param string $method Class method to be called upon invocation.
	 */
	private static function create_subcommand( $parent, $name, $callable, $reflection ) {
		$doc_comment = self::get_doc_comment( $reflection );
		$docparser = self::get_inherited_docparser( $doc_comment, $reflection );

		while ( $docparser->has_tag( 'inheritdoc' ) ) {
			$inherited_method = $reflection->getDeclaringClass()->getParentClass()->getMethod( $reflection->name );

			$doc_comment = self::get_doc_comment( $inherited_method );
			$docparser   = new DocParser( $doc_comment );
		}

		if ( is_array( $callable ) ) {
			if ( ! $name ) {
				$name = $docparser->get_tag( 'subcommand' );
			}

			if ( ! $name ) {
				$name = $reflection->name;
			}
		}
		if ( ! $doc_comment ) {
			EE::debug( null === $doc_comment ? "Failed to get doc comment for {$name}." : "No doc comment for {$name}.", 'commandfactory' );
		}

		$when_invoked = function ( $args, $assoc_args ) use ( $callable ) {
			if ( is_array( $callable ) ) {
				$callable[0] = is_object( $callable[0] ) ? $callable[0] : new $callable[0];
				call_user_func( array( $callable[0], $callable[1] ), $args, $assoc_args );
			} else {
				call_user_func( $callable, $args, $assoc_args );
			}
		};

		return new Subcommand( $parent, $name, $docparser, $when_invoked );
	}

	/**
	 * Create a new Composite command instance.
	 *
	 * @param mixed $parent The new command's parent Root or Composite command
	 * @param string $name Represents how the command should be invoked
	 * @param mixed $callable
	 */
	private static function create_composite_command( $parent, $name, $callable ) {
		$reflection = new ReflectionClass( $callable );
		$doc_comment = self::get_doc_comment( $reflection );
		if ( ! $doc_comment ) {
			EE::debug( null === $doc_comment ? "Failed to get doc comment for {$name}." : "No doc comment for {$name}.", 'commandfactory' );
		}
		$docparser = new DocParser( $doc_comment );

		$container = new CompositeCommand( $parent, $name, $docparser );

		foreach ( $reflection->getMethods() as $method ) {
			$method_doc_comment = self::get_doc_comment( $method );
			if ( ! self::is_good_method( $method ) || self::should_ignore_method( $method_doc_comment, $method ) ) {
				continue;
			}

			$class      = is_object( $callable ) ? $callable : $reflection->name;
			$subcommand = self::create_subcommand( $container, false, array( $class, $method->name ), $method );

			$subcommand_name = $subcommand->get_name();

			$container->add_subcommand( $subcommand_name, $subcommand );
		}

		return $container;
	}

	/**
	 * Create a new command namespace instance.
	 *
	 * @param mixed $parent The new namespace's parent Root or Composite command.
	 * @param string $name Represents how the command should be invoked
	 * @param mixed $callable
	 */
	private static function create_namespace( $parent, $name, $callable ) {
		$reflection = new ReflectionClass( $callable );
		$doc_comment = self::get_doc_comment( $reflection );
		if ( ! $doc_comment ) {
			EE::debug( null === $doc_comment ? "Failed to get doc comment for {$name}." : "No doc comment for {$name}.", 'commandfactory' );
		}
		$docparser = new DocParser( $doc_comment );

		return new CommandNamespace( $parent, $name, $docparser );
	}

	/**
	 * Check whether a method is actually callable.
	 *
	 * @param ReflectionMethod $method
	 * @return bool
	 */
	private static function is_good_method( $method ) {
		return $method->isPublic() && ! $method->isStatic() && 0 !== strpos( $method->getName(), '__' );
	}

	/**
	 * @param string           $doc_comment
	 * @param ReflectionMethod $reflection
	 */
	private static function get_inherited_docparser( $doc_comment, $reflection ) {
		$docparser = new DocParser( $doc_comment );
		while ( $docparser->has_tag( 'inheritdoc' ) ) {
			$inherited_method = $reflection->getDeclaringClass()->getParentClass()->getMethod( $reflection->name );

			$doc_comment = self::get_doc_comment( $inherited_method );
			$docparser   = new DocParser( $doc_comment );
		}

		return $docparser;
	}

	/**
	 * Check whether a method should be ignored.
	 *
	 * @param ReflectionMethod $method
	 *
	 * @return bool
	 */
	private static function should_ignore_method( $doc_comment, $reflection ) {
		$docparser = self::get_inherited_docparser( $doc_comment, $reflection );

		return $docparser->has_tag( 'ignorecommand' );
	}

	/**
	 * Gets the document comment. Caters for PHP directive `opcache.save comments` being disabled.
	 *
	 * @param ReflectionMethod|ReflectionClass|ReflectionFunction $reflection Reflection instance.
	 * @return string|false|null Doc comment string if any, false if none (same as `Reflection*::getDocComment()`), null if error.
	 */
	private static function get_doc_comment( $reflection ) {
		$doc_comment = $reflection->getDocComment();

		if ( false !== $doc_comment || ! ( ini_get( 'opcache.enable_cli' ) && ! ini_get( 'opcache.save_comments' ) ) ) {
			// Either have doc comment, or no doc comment and save comments enabled - standard situation.
			if ( ! getenv( 'EE_TEST_GET_DOC_COMMENT' ) ) {
				return $doc_comment;
			}
		}

		$filename = $reflection->getFileName();

		if ( isset( self::$file_contents[ $filename ] ) ) {
			$contents = self::$file_contents[ $filename ];
		} elseif ( is_readable( $filename ) && ( $contents = file_get_contents( $filename ) ) ) {
			self::$file_contents[ $filename ] = $contents = explode( "\n", $contents );
		} else {
			EE::debug( "Could not read contents for filename '{$filename}'.", 'commandfactory' );
			return null;
		}

		return self::extract_last_doc_comment( implode( "\n", array_slice( $contents, 0, $reflection->getStartLine() ) ) );
	}

	/**
	 * Returns the last doc comment if any in `$content`.
	 *
	 * @param string $content The content, which should end at the class or function declaration.
	 * @return string|bool The last doc comment if any, or false if none.
	 */
	private static function extract_last_doc_comment( $content ) {
		$content = trim( $content );
		$comment_end_pos = strrpos( $content, '*/' );
		if ( false === $comment_end_pos ) {
			return false;
		}
		// Make sure comment end belongs to this class/function.
		if ( preg_match_all( '/(?:^|[\s;}])(?:class|function)\s+/', substr( $content, $comment_end_pos + 2 ), $dummy /*needed for PHP 5.3*/ ) > 1 ) {
			return false;
		}
		$content = substr( $content, 0, $comment_end_pos + 2 );
		if ( false === ( $comment_start_pos = strrpos( $content, '/**' ) ) || $comment_start_pos + 2 === $comment_end_pos ) {
			return false;
		}
		// Make sure comment start belongs to this comment end.
		if ( false !== ( $comment_end2_pos = strpos( substr( $content, $comment_start_pos ), '*/' ) ) && $comment_start_pos + $comment_end2_pos < $comment_end_pos ) {
			return false;
		}
		// Allow for '/**' within doc comment.
		$subcontent = substr( $content, 0, $comment_start_pos );
		while ( false !== ( $comment_start2_pos = strrpos( $subcontent, '/**' ) ) && false === strpos( $subcontent, '*/', $comment_start2_pos ) ) {
			$comment_start_pos = $comment_start2_pos;
			$subcontent = substr( $subcontent, 0, $comment_start_pos );
		}
		return substr( $content, $comment_start_pos, $comment_end_pos + 2 );
	}
}
