<?php

/*
 * This file is part of the Acme PHP project.
 *
 * (c) Titouan Galopin <galopintitouan@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace AcmePhp\Cli\Exception;

/**
 * @author Titouan Galopin <galopintitouan@gmail.com>
 */
class AcmeCliActionException extends AcmeCliException
{
    public function __construct($actionName, \Exception $previous = null)
    {
        parent::__construct(sprintf('An exception was thrown during action "%s"', $actionName), $previous);
    }
}
