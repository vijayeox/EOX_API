<?php

namespace File\Controller;

use Oxzion\Service\FileService;
use Oxzion\Controller\AbstractApiControllerHelper;
use Oxzion\EntityNotFoundException;
use Oxzion\ServiceException;
use \Exception;
use Oxzion\Utils\FileUtils;

class FileCallbackController extends AbstractApiControllerHelper
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
    public function updateFileAction()
    {
        $data = $this->extractPostData();
        try {
            $fileId = !empty($data['id']) ? $data['id'] : (!empty($data['uuid']) ? $data['uuid'] : null);
            if (!$fileId) {
                throw new EntityNotFoundException("Invalid File Id");
            }
            $this->fileService->updateFileAttributes($fileId);
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }

        return $this->getSuccessResponseWithData($data, 200);
    }

    public function updateRygForFileAction()
    {
        $params = array_merge($this->extractPostData(), $this->params()->fromRoute());
        try {
            $this->fileService->bulkUpdateFileRygStatus($params);
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
        return $this->getSuccessResponse('Success', 200);
    }
    
    public function documentFinalized($data)
    {
        FileUtils::storeFile($data['file'], $this->config['APP_ESIGN_FOLDER'].$data['refId']);
    }
}
