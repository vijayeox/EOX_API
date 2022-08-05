<?php
namespace Callback\Controller;

use Callback\Service\DocumentCallbackService;
use Exception;
use Oxzion\Controller\AbstractApiControllerHelper;

class DocumentCallbackController extends AbstractApiControllerHelper
{
    private $documentCallbackService;
    private $log;
    /**
     * @ignore __construct
     */
    public function __construct(DocumentCallbackService $documentCallbackService)
    {
        $this->documentCallbackService = $documentCallbackService;
        $this->log = $this->getLogger();
    }

    public function saveExcelAction() {
        $params = $this->extractPostData();
        $this->log->info("Params for document callback action- " . json_encode($params));
        $response = $this->documentCallbackService->saveExcel($params);
        if ($response) {
            $this->log->info("Document save successfully");
            return $this->getSuccessResponseWithData(json_decode($response['body'], true));
        }
        return $this->getErrorResponse("Document save has failed", 400);
    }
}