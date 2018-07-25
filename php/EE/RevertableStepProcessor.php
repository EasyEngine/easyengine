<?php

namespace EE;

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
	 * @param callable $up_step Callable that will be called when step is to be executed
	 * @param callable $down_step Callable that will be called when step is to be reverted
	 * @param string $context Context of step. It will be used to display error.
	 */
	public function add_step( callable $up_step, callable $down_step, string $context = null ) {
		$this->steps[] = [
			'up' => $up_step,
			'down' => $down_step,
			'context' => $context,
		];

		return $this; // Returns this to enable method chaining
	}

	/**
	 * Adds new step and executes pending steps.
	 *
	 * @param callable $up_step Callable that will be called when step is to be executed
	 * @param callable $down_step Callable that will be called when step is to be reverted
	 * @param string $context Context of step. It will be used to display error.
	 */
	public function execute_step( callable $up_step, callable $down_step, string $context = null ) {
		$this->add_step( $up_step, $down_step, $context );
		return $this->execute();
	}

	/**
	 * Executes all pending steps. Reverts the steps if any one step throws error.
	 */
	public function execute() {
		for ( $i = $this->execution_index ; $i < count( $this->steps ); $i++ ) {
			try {
				call_user_func( $this->steps[ $i ]['up'] );
				$this->execution_index++;
			} catch ( \Exception $e ) {
				$this->steps[ $i ]['up'];
				$context = $this->steps[ $i ]['context'];
				$exception_message = $e->getMessage();

				echo  "Encountered error while processing \"$context\". Exception: $exception_message\n";

				$this->rollback();
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
			try {
				call_user_func( $this->steps[ $this->execution_index ]['down'] );
				$this->execution_index--;
			} catch ( \Exception $e ) {
				echo 'Encountered error while reverting step. If possible, do it manually' ;
			}
		}
	}
}
