<?php

namespace App\Controller;

use Oxzion\Model\App;
use Oxzion\Model\AppTable;
use Oxzion\Service\AppService;
use Exception;
use Oxzion\Controller\AbstractApiControllerHelper;
use Oxzion\ValidationException;
use Zend\Db\Adapter\AdapterInterface;
use Oxzion\Service\AppRegistryService;

class AppRegisterController extends AbstractApiControllerHelper
{
    /**
     * @var AppService Instance of AppService Service
     */
    private $appService;
    private $appRegistryService;
    private $log;
    /**
     * @ignore __construct
     */
    public function __construct(AppTable $table, AppService $appService, AdapterInterface $dbAdapter, AppRegistryService $appRegistryService)
    {
        $this->setIdentifierName('appId');
        $this->log = $this->getLogger();
        $this->appService = $appService;
        $this->appRegistryService = $appRegistryService;
    }
    /**
     * App Register API
     * @api
     * @link /app/register
     * @method POST
     * @param array $data
     */
    public function appregisterAction()
    {
        $data = $this->extractPostData();
        $this->log->info(__CLASS__ . "-> \n Create App Registry- " . print_r($data, true));
        try {
            $this->appService->registerApps($data);
        } catch (ValidationException $e) {
            $response = ['data' => $data, 'errors' => $e->getErrors()];
            return $this->getErrorResponse("Validation Errors", 404, $response);
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->getErrorResponse($e->getMessage(), 404);
        }
        return $this->getSuccessResponseWithData($data, 200);
    }

    /**
     * GET App Properties API
     * @api
     * @link /app/:appId/account/:accountId/appProperties
     * @method GET
     * @return array App properties
     */
    public function getAppPropertiesAction()
    {
        $params = array_merge($this->extractPostData(), $this->params()->fromRoute());
        try {
            $result = $this->appRegistryService->getAppProperties($params);
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->getErrorResponse("App Properties Error!!", 400);
        }
        return $this->getSuccessResponseWithData($result, 200);
    }

    /**
     * GET Account Based On App Service Type API
     * @api
     * @link /app/:appId/getAccounts/:serviceType
     * @method GET
     * @return array App properties
     */
    public function getAccountOnServiceTypeAction()
    {
        
        $params = array_merge($this->extractPostData(), $this->params()->fromRoute());
        $params['filterParams'] = $this->params()->fromQuery(); 
        
        try {
            $result = $this->appService->getAccountOnServiceType($params);
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->getErrorResponse("Error!!!", 400);
        }
        //print_r($this->getSuccessResponseWithData($result, 200));
        //die;
        return $this->getSuccessResponseWithData($result, 200);
    }
}
