<?php

/**
 * Base class for Site command
 *
 * @package ee
 */
abstract class EE_Site_Command {

	public function __construct() {}

	public function _list( $args, $assoc_args ) {}

	public function delete( $args, $assoc_args ) {}

	public function create( $args, $assoc_args ) {}

	public function up( $args, $assoc_args ) {}

	public function down( $args, $assoc_args ) {}

}

