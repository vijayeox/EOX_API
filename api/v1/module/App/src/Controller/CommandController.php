<?php
namespace App\Controller;

/**
* Pipleline Api
*/
use Oxzion\Service\CommandService;
use Oxzion\Controller\AbstractApiControllerHelper;
use Oxzion\ValidationException;
use Oxzion\EntityNotFoundException;

class CommandController extends AbstractApiControllerHelper
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
    public function executeCommandsAction()
    {
        $params = array_merge($this->extractPostData(), $this->params()->fromRoute(), $this->params()->fromQuery());
        $appUuid = $this->params()->fromRoute()['appId'];
        unset($params['method']);
        unset($params['controller']);
        unset($params['action']);
        unset($params['access']);
        try {
            if (isset($params['commands'])) {
                $allowed = false;
                foreach ($params['commands'] as $key => $command) {
                    if ($command['command'] == 'verify_user') {
                        $allowed = true;
                    } elseif ($command['command']=='delegate') {
                        $allowed = true;
                    } else {
                        $allowed = false;
                    }
                }
                if ($allowed) {
                    $response = $this->commandService->runCommand($params, $this->getRequest());
                    if ($response && is_array($response)) {
                        $this->log->info(":Pipleline Service Executed - " . print_r($response, true));
                        return $this->getSuccessResponseWithData($response, 200);
                    } else {
                        return $this->getSuccessResponse();
                    }
                } else {
                    $this->log->info("Commands Access not Allowed -" . $params['commands']);
                    return $this->getErrorResponse("Command Access Restricted", 401, null);
                }
            }
        } catch (ValidationException $e) {
            $this->log->error(":Exception while Performing Service Task-" . $e->getMessage(), $e);
            $response = ['data' => $params, 'errors' => $e->getErrors()];
            return $this->getErrorResponse("Validation Errors", 406, $response);
        } catch (EntityNotFoundException $e) {
            $this->log->info(":Entity Not found -" . $e->getMessage());
            $response = ['data' => $params];
            return $this->getErrorResponse($e->getMessage(), 404, $response);
        } catch (\Exception $e) {
            $this->log->error(":Error -" . $e->getMessage(), $e);
            $response = ['data' => $params];
            return $this->getErrorResponse($e->getMessage(), 500, $response);
        }
    }
}
