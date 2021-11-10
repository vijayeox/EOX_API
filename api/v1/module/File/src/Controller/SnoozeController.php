<?php

namespace File\Controller;

use Oxzion\Service\FileService;
use Oxzion\Controller\AbstractApiControllerHelper;
use Oxzion\EntityNotFoundException;
use Oxzion\ServiceException;
use \Exception;
use Oxzion\Utils\FileUtils;

class SnoozeController extends AbstractApiControllerHelper
{
    /**
    * @var FileService Instance of FileService
    */
    private $fileService;
    private $log;
    /**
    * @ignore __construct
    */
    public function __construct(FileService $fileService)
    {
        $this->fileService = $fileService;
        $this->log = $this->getLogger();
    }

    /**
    * Update File Data API
    * @api
    * @link /callback/file
    * @method POST
    * @param array $data Array of file data
    * @return array Returns a JSON Response with Status Code.
    */
    public function snoozeFileAction(){
        // $fileUuid =  $this->params()->fromRoute()['fileId'];
        // $isSnoozed = $this->extractPostData()['snooze'];
        $params = array_merge($this->extractPostData(), $this->params()->fromRoute());
        try {
            $result = $this->fileService->snoozeFile($params);
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }

        $response = $result=="1"?"File has been snoozed":"File is alive";
        return $this->getSuccessResponse($response);
    }
}
