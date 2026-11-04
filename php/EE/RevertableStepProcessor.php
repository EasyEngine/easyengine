<?php

namespace EE;

use EE;

/**
 * RevertibleStepProcessor
 *
 * This class is used to ensure that a series of steps have been executed successfully.
 * If any one step fails while executing, All executed steps will be reverted.
 */
class RevertableStepProcessor {

	/** @var array Contains array of steps */
	private $steps = [];

	/** @var int Keeps track of steps executed. All items in $steps till this index have been executed */
	private $execution_index = 0;

	/** @var bool Whether an `EE::error()`/`EE::halt()` inside a step throws, so the rollback still runs, instead of exiting. */
	private $capture_exit;

	/**
	 * @param bool $capture_exit Turn exits inside up and down steps into failures of that step.
	 */
	public function __construct( $capture_exit = false ) {
		$this->capture_exit = (bool) $capture_exit;
	}

	/**
	 * Adds a new step.
	 *
	 * @param string   $context     Context of step. It will be used to display error.
	 * @param callable $up_step     Callable that will be called when step is to be executed
	 * @param callable $down_step   Callable that will be called when step is to be reverted
	 * @param array    $up_params   Parameters to pass to $up_step function
	 * @param array    $down_params Parameters to pass to $down_step function
	 *
	 * @return RevertableStepProcessor Returns current object for chaining methods.
	 */
	public function add_step( string $context, callable $up_step, callable $down_step = null, array $up_params = null, array $down_params = null ) {
		$this->steps[] = [
			'up'          => $up_step,
			'up_params'   => $up_params,
			'down'        => $down_step,
			'down_params' => $down_params,
			'context'     => $context,
		];

		return $this; // Returns this to enable method chaining
	}

	/**
	 * Executes all pending steps. Reverts the steps if any one step throws error.
	 *
	 * @return boolean Returns if the pending steps were executed successfully.
	 */
	public function execute( $show_error = true ) {
		$steps_to_execute = array_slice( $this->steps, $this->execution_index );

		foreach ( $steps_to_execute as $step ) {
			$context = $step['context'];
			try {
				EE::debug( "Executing $context... " );
				$this->call( $step['up'], $step['up_params'] );
				$this->execution_index ++;
				EE::debug( "Executed $context." );
			} catch ( \Throwable $e ) {
				$exception_message = self::describe( $e );
				$callable          = EE\Utils\get_callable_name( $step['up'] );
				if ( $show_error ) {
					EE::error( "Error while executing $context. $callable: $exception_message", false );
				}
				$this->rollback();
				$this->steps = [];

				return false;
			}
		}

		return true;
	}

	/**
	 * Rolls back all executed steps.
	 */
	public function rollback() {
		while ( $this->execution_index >= 0 ) {
			if ( ! array_key_exists( $this->execution_index, $this->steps ) ) {
				$this->execution_index--;
				continue;
			}
			$step    = $this->steps[ $this->execution_index ];
			$context = $step['context'];
			try {
				EE::debug( "Reverting $context... " );
				if ( null !== $step['down'] ) {
					$this->call( $step['down'], $step['down_params'] );
				}
				EE::debug( "Reverted $context" );
			} catch ( \Throwable $e ) {
				$exception_message = self::describe( $e );
				EE::debug( "Encountered error while reverting $context: $exception_message. If possible, do it manually" );
			} finally {
				$this->execution_index --;
			}
		}
	}

	/**
	 * Calls a step callable, capturing exits if this processor does.
	 *
	 * @param callable   $callable Step callable.
	 * @param array|null $params   Its parameters.
	 */
	private function call( $callable, $params ) {
		if ( ! $this->capture_exit ) {
			call_user_func_array( $callable, $params ?? [] );

			return;
		}

		$previous = EE::set_capture_exit( true );
		try {
			call_user_func_array( $callable, $params ?? [] );
		} finally {
			EE::set_capture_exit( $previous );
		}
	}

	/**
	 * @param \Throwable $e Failure of a step.
	 *
	 * @return string
	 */
	private static function describe( \Throwable $e ) {
		// EE::error() has already printed its message.
		if ( $e instanceof ExitException ) {
			return sprintf( 'exited with code %d', $e->getCode() );
		}

		return $e->getMessage();
	}
}
