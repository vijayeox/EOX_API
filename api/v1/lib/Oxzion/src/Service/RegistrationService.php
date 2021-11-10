<?php

namespace Oxzion\Service;

use Exception;
use Oxzion\Auth\AuthConstants;
use Oxzion\Auth\AuthContext;
use Oxzion\ServiceException;
use Oxzion\Service\AbstractService;
use Oxzion\Service\AccountService;
use Oxzion\Service\AppRegistryService;

class RegistrationService extends AbstractService
{
    private $accountService;
    private $appRegistryService;

    public function __construct($config, $dbAdapter, AccountService $accountService, AppRegistryService $appRegistryService)
    {
        parent::__construct($config, $dbAdapter);
        $this->accountService = $accountService;
        $this->appRegistryService = $appRegistryService;
    }

    public function registerAccount(&$data)
    {
        $this->accountService->registerAccount($data);
    }
}
