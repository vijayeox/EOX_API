<?php
namespace Oxzion\Integrations;

use Exception;
use Oxzion\Service\AbstractService;
use Oxzion\EntityNotFoundException;

class DeltaService extends AbstractService
{
    public function __construct($config, $dbAdapter)
    {
        parent::__construct($config, $dbAdapter);
    }

    public function testEndpoint($data)
    {
        $this->logger->info("The data coming in is ----".print_r($data,true));
    }

}
