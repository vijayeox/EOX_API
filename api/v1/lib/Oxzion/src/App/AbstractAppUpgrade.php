<?php
namespace Oxzion\App;

use Logger;

abstract class AbstractAppUpgrade implements AppUpgrade
{
    protected $logger;
    
    public function __construct()
    {
        $this->logger = Logger::getLogger(__CLASS__);
    }
}
