<?php
namespace App\Controller;

/**
* Pipleline Api
*/
use Oxzion\Service\CommandService;
use Zend\Db\Adapter\AdapterInterface;
use Oxzion\Controller\AbstractApiController;
use Oxzion\ValidationException;
use Oxzion\Workflow\Camunda\WorkflowException;
use Oxzion\DelegateException;
use Zend\Http\Request as HttpRequest;
use Exception;

class PipelineController extends AbstractApiController
{
    private $commandService;
    protected $log;
    /**
    * @ignore __construct
    */
    public function __construct(CommandService $commandService)
    {
        $this->commandService = $commandService;
        $this->log = $this->getLogger();
    }
    public function executePipelineAction()
    {
        $this->log->info("PIPELINE CONTROLLER");
        $params = array_merge($this->extractPostData(), $this->params()->fromRoute());
        $params = array_merge($params, $this->params()->fromQuery());
        $appUuid = $this->params()->fromRoute()['appId'];
        $params['appId'] = $appUuid;
        if (isset($params['commands'])) {
            if (is_string($params['commands'])) {
                if ($commands = json_decode($params['commands'], true)) {
                    $params['commands'] = $commands;
                }
            }
        }
        unset($params['method']);
        unset($params['controller']);
        unset($params['action']);
        unset($params['access']);
        try {
            $response = $this->commandService->runCommand($params, $this->getRequest());
            if ($response && is_array($response)) {
                $this->log->info(":Pipleline Service Executed - " . print_r($response, true));
                return $this->getSuccessResponseWithData($response, 200);
            } else {
                return $this->getSuccessResponse();
            }
        } catch (WorkflowException $e) {
            $this->log->info("-Error while claiming - " . $e->getReason() . ": " . $e->getMessage());
            if ($e->getReason() == 'TaskAlreadyClaimedException') {
                return $this->getErrorResponse("Task has already been claimed by someone else", 409);
            }
            return $this->getErrorResponse($e->getMessage(), 409);
        } catch (DelegateException $e) {
            return $this->getErrorResponse($e->getMessage(), 409);
        } 
        catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
    }

    public function executeBatchPipelineAction()
    {
        $this->log->info("BATCH PIPELINE CONTROLLER");
        $params = array_merge($this->extractPostData(), $this->params()->fromRoute());
        $params = array_merge($params, $this->params()->fromQuery());
        $appUuid = $this->params()->fromRoute()['appId'];
        $params['appId'] = $appUuid;
        if (isset($params['commands'])) {
            if (is_string($params['commands'])) {
                if ($commands = json_decode($params['commands'], true)) {
                    $params['commands'] = $commands;
                }
            }
        }
        unset($params['method']);
        unset($params['controller']);
        unset($params['action']);
        unset($params['access']);
        try {
            $this->commandService->batchProcess($params, $this->getRequest());
            return $this->getSuccessResponse();
        } catch (Exception $e) {
            $this->log->error(":Error -" . $e->getMessage(), $e);
            $response = ['data' => $params];
            return $this->getErrorResponse("An error occurred! Please try again later.", 500, $response);
        }
    }
}
