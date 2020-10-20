<?php
namespace App\Controller;

/**
 * File Api
 */
use Oxzion\Controller\AbstractApiController;
use Oxzion\Encryption\Crypto;
use Oxzion\Model\File;
use Oxzion\Model\FileTable;
use Oxzion\ServiceException;
use Oxzion\Service\FileService;
use Oxzion\ValidationException;
use Oxzion\AccessDeniedException;
use Oxzion\Utils\ArtifactUtils;
use Oxzion\EntityNotFoundException;
use Zend\Db\Adapter\AdapterInterface;


class FileController extends AbstractApiController
{
    private $fileService;
    /**
     * @ignore __construct
     */
    public function __construct(FileTable $table, fileService $fileService, AdapterInterface $dbAdapter)
    {
        parent::__construct($table, File::class);
        $this->setIdentifierName('id');
        $this->fileService = $fileService;
    }
    /**
     * Create File API
     * @api
     * @link /app/appId/form
     * @method POST
     * @param array $data Array of elements as shown
     * <code> {
     *               id : integer,
     *               name : string,
     *               Fields from File
     *   } </code>
     * @return array Returns a JSON Response with Status Code and Created File.
     */
    public function create($data)
    {
        $data['app_id'] = $this->params()->fromRoute()['appId'];
        $formId = $this->params()->fromRoute()['formId'];
        if ($formId) {
            $data['form_id'] = $formId;
        } else {
            return $this->getFailureResponse("Form id not Found", $data);
        }
        try {
            $count = $this->fileService->createFile($data);
        } catch (ValidationException $e) {
            $response = ['data' => $data, 'errors' => $e->getErrors()];
            return $this->getErrorResponse("Validation Errors", 404, $response);
        }catch(ServiceException $e){
            return $this->getErrorResponse($e->getMessage(),404);
        }
        return $this->getSuccessResponseWithData($data, 201);
    }

    /**
     * GET List Files API
     * @api
     * @link /app/appId/form
     * @method GET
     * @return array Returns a JSON Response list of Files based on Access.
     */
    public function getList()
    {
        return $this->getInvalidMethod();
    }
    /**
     * Update File API
     * @api
     * @link /app/appId/form[/:id]
     * @method PUT
     * @param array $id ID of File to update
     * @param array $data
     * @return array Returns a JSON Response with Status Code and Created File.
     */
    public function update($id, $data)
    {
        $appUuId = $this->params()->fromRoute()['appId'];
        $formUuId = $this->params()->fromRoute()['formId'];
        if ($formUuId) {
            $data['form_uuid'] = $formUuId;
        }
        if ($appUuId) {
            $data['app_uuid'] = $appUuId;
        }
        try {
            $count = $this->fileService->updateFile($data, $id);
            // var_dump($count);exit;
        } catch (ValidationException $e) {
            $response = ['data' => $data, 'errors' => $e->getErrors()];
            return $this->getErrorResponse("Validation Errors", 404, $response);
        }
        catch (EntityNotFoundException $e) {
            $response = ['data' => $data, 'errors' => $e->getMessage()];
            return $this->getErrorResponse("Entity Not Found", 404, $response);
        }
        if ($count == 0) {
            return $this->getErrorResponse("Entity not found for id - $id", 404);
        }
        return $this->getSuccessResponseWithData($data, 200);
    }
    /**
     * Delete File API
     * @api
     * @link /app/appId/form[/:id]
     * @method DELETE
     * @param $id ID of File to Delete
     * @return array success|failure response
     */
    public function delete($id)
    {
        try {
            $response = $this->fileService->deleteFile($id);
        } catch (ServiceException $e) {
            return $this->getErrorResponse($e->getMessage(), 404);
        }
        if ($response == 0) {
            return $this->getErrorResponse("File not found", 404, ['id' => $id]);
        }
        return $this->getSuccessResponse();
    }
    /**
     * GET File API
     * @api
     * @link /app/appId/form[/:id]
     * @method GET
     * @param $id ID of File
     * @return array $data
     * @return array Returns a JSON Response with Status Code and Created File.
     */
    public function get($id)
    {
        $result = $this->fileService->getFile($id);
        if ($result == 0) {
            return $this->getErrorResponse("File not found", 404, ['id' => $id]);
        }
        return $this->getSuccessResponseWithData($result);
    }

    public function getDocumentAction()
    {
        $params = array_merge($this->extractPostData(), $this->params()->fromRoute());

        $crypto = new Crypto();
        $file = $crypto->decryption($params['documentName']);
        if(file_exists($file)){
            if (!headers_sent()) {
                header('Content-Type: application/octet-stream');
                header("Content-Transfer-Encoding: Binary");
                header("Content-disposition: attachment; filename=\"" . basename($file) . "\"");
            }
            try {
                $fp = @fopen($file, 'rb');
                fpassthru($fp);
                fclose($fp);
                $this->response->setStatusCode(200);
                return $this->response;
            } catch (Exception $e) {
                return $this->getErrorResponse($e->getMessage(), 500);
            }
        } else {
            print("FILE NOT");
            return $this->getErrorResponse("Document not Found", 404);
        }
    }

    public function getFileDataAction(){
        $params = array_merge($this->extractPostData(), $this->params()->fromRoute());
        try{
            $result = $this->fileService->getFileByWorkflowInstanceId($params['workflowInstanceId']);
            if($result == 0){
                return $this->getErrorResponse("File not found", 404, ['id' => $params['workflowInstanceId']]);
            }
            return $this->getSuccessResponseWithData($result, 200);

        }catch(Exception $e){
            return $this->getErrorResponse($e->getMessage(),404);
        }

    }

     public function sendReminderAction($data)
    {

    }
    /**
    * GET List Entitys API
    * @api
    * @link /app/:appId/file/search
    * @method GET
    * @return array Returns a JSON Response list of Entitys based on Access.
    */
    public function getFileListAction()
    {
        $appUuid = $this->params()->fromRoute()['appId'];
        $params = array_merge($this->extractPostData(), $this->params()->fromRoute());
        $filterParams = $this->params()->fromQuery();
        try {
            $result = $this->fileService->getFileList($appUuid,$params,$filterParams);
        } catch (ValidationException $e) {
            $response = ['errors' => $e->getErrors()];
            $this->log->error($e->getMessage(), $e);
            return $this->getErrorResponse("Validation Errors", 404, $response);
        } catch (AccessDeniedException $e) {
            $response = ['errors' => $e->getErrors()];
            $this->log->error($e->getMessage(), $e);
            return $this->getErrorResponse($e->getMessage(), 403, $response);
        } catch (ServiceException $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->getErrorResponse($e->getMessage(), 404);
        }
        return $this->getSuccessResponseDataWithPagination($result['data'], $result['total']);
    }


    /**
    * GET List Entitys API
    * @api
    * @link /app/appId/search
    * @method GET
    * @return array Returns a JSON Response list of Entitys based on Access.
    */
    public function getFileListCommandAction()
    {
        $appUuid = $this->params()->fromRoute()['appId'];
        $params = array_merge($this->extractPostData(), $this->params()->fromRoute());
        $commandList = $this->params()->fromRoute()['commands'];
        $commandsArray = explode("+",$commandList);
        $filterParams = $this->params()->fromQuery();
        try {
        foreach ($commandsArray as $command) {
            switch ($command) {
                case 'myfiles':
                        $params['status'] = 'Completed';
                        $result['myfiles'] = $this->fileService->getFileList($appUuid,$params,$filterParams);
                    break;
                case 'assignments':
                        $result['assignments'] = $this->fileService->getAssignments($appUuid,$filterParams);
                    break;
                default:
                    break;
            }
        }
        } catch (ValidationException $e) {
            $response = ['errors' => $e->getErrors()];
            return $this->getErrorResponse("Validation Errors", 404, $response);
        } catch (AccessDeniedException $e) {
            $response = ['errors' => $e->getErrors()];
            return $this->getErrorResponse($e->getMessage(), 403, $response);
        } catch (ServiceException $e) {
            return $this->getErrorResponse($e->getMessage(), 404);
        }
        return $this->getSuccessResponseWithData($result);
    }

    public function getFileDocumentListAction()
    {
        $params = $this->params()->fromRoute();
        try {
            $result = $this->fileService->getFileDocumentList($params);
        } catch (ValidationException $e) {
            $response = ['errors' => $e->getErrors()];
            return $this->getErrorResponse("Validation Errors", 404, $response);
        } catch (AccessDeniedException $e) {
            $response = ['errors' => $e->getErrors()];
            return $this->getErrorResponse($e->getMessage(), 403, $response);
        }
        return $this->getSuccessResponseWithData($result, 200);
    }
    public function reIndexAction()
    {
        $params = array_merge($this->extractPostData(), $this->params()->fromRoute());
        try {
            $result = $this->fileService->reIndexFile($params);
        } catch (ValidationException $e) {
            $response = ['errors' => $e->getErrors()];
            return $this->getErrorResponse("Validation Errors", 404, $response);
        } catch (AccessDeniedException $e) {
            $response = ['errors' => $e->getErrors()];
            return $this->getErrorResponse($e->getMessage(), 403, $response);
        }
        return $this->getSuccessResponseWithData($result, 200);
    }
}
