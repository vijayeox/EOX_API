<?php

namespace Oxzion\AppDelegate;

use Logger;

abstract class AbstractAppDelegate implements AppDelegate
{
    use UserContextTrait;
    protected $logger;

    public function __construct()
    {
        $this->logger = Logger::getLogger(__CLASS__);
    }
}
