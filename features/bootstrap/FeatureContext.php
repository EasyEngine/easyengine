<?php

use Behat\Behat\Context\Context;


use Behat\Gherkin\Node\PyStringNode,
	Behat\Gherkin\Node\TableNode,
	Behat\Gherkin\Node\OutlineNode;

use Behat\Behat\Event\FeatureEvent;

class FeatureContext implements Context {
	public $command;
	public $webroot_path;
	
	
	/**
     * @When I run :arg1
     */
    public function iRun($arg1)
    {
        throw new PendingException();
    }

    /**
     * @Then STDOUT should return exactly
     */
    public function stdoutShouldReturnExactly(PyStringNode $string)
    {
        throw new PendingException();
    }

    /**
     * @Then STDERR should be empty
     */
    public function stderrShouldBeEmpty()
    {
        throw new PendingException();
    }

    /**
     * @Then The :arg1 db entry should be removed
     */
    public function theDbEntryShouldBeRemoved($arg1)
    {
        throw new PendingException();
    }

    /**
     * @Then The :arg1 webroot should be removed
     */
    public function theWebrootShouldBeRemoved($arg1)
    {
        throw new PendingException();
    }

    /**
     * @Then Following containers of site :arg1 should be removed:
     */
    public function followingContainersOfSiteShouldBeRemoved($arg1, TableNode $table)
    {
        throw new PendingException();
    }
}
