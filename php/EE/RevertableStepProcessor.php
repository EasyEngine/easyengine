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

	/**
	 * Adds a new step.
	 *
	 * @param string $context Context of step. It will be used to display error.
	 * @param callable $up_step Callable that will be called when step is to be executed
	 * @param callable $down_step Callable that will be called when step is to be reverted
	 * @param array $up_params Parameters to pass to $up_step function
	 * @param array $down_params Parameters to pass to $down_step function
	 * @return RevertableStepProcessor Returns current object for chaining methods.
	 */
	public function add_step( string $context, callable $up_step, callable $down_step = null, array $up_params = null, array $down_params = null  ) {
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
	 * @return boolean Returns if the pending steps were executed successfully.
	 */
	public function execute() {
		$steps_to_execute = array_slice( $this->steps, $this->execution_index );

		foreach ( $steps_to_execute as $step ) {
			$context = $step['context'];
			try {
				EE::debug( "Executing $context... " );
				call_user_func_array( $step['up'], $step['up_params'] ?? []);
				$this->execution_index++;
				EE::debug( "Executed $context." );
			} catch ( \Exception $e ) {
				$exception_message = $e->getMessage();
				$callable = EE\Utils\get_callable_name( $step['up'] );
				EE::error( "Encountered error while processing $context in $callable. Exception: $exception_message", false );
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
			$step = $this->steps[ $this->execution_index ];
			$context = $step['context'];
			try {
				EE::debug( "Reverting $context... " );
				if( null !== $step['down'] ) {
					call_user_func_array( $step['down'], $step['down_params'] ?? [] ) ;
				}
				EE::debug( "Reverted $context" );
			} catch ( \Exception $e ) {
				$exception_message = $e->getMessage();
				EE::debug( "Encountered error while reverting $context: $exception_message. If possible, do it manually" );
			} finally {
				$this->execution_index--;
			}
		}
	}
}
