<?php

namespace EE\Migration;

abstract class Base {

    public $status = 'incomplete';

    abstract public function up();
    abstract public function down();
}