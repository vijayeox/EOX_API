<?php
namespace Esign\Controller;

use Oxzion\Controller\AbstractApiController;
use Oxzion\Service\EsignService;
use Zend\Db\Adapter\AdapterInterface;
use Exception;

class EsignController extends AbstractApiController
{
    private $esignService;

    /**
     * @ignore __construct
     */
    public function __construct(EsignService $esignService)
    {
        parent::__construct();
        $this->esignService = $esignService;
    }

    /**
     * GET sttus API
     * @api
     * @link
     * @method GET
     * @return get status
     */
    public function getStatusAction()
    {
        $docId = $this->params()->fromRoute()['docId'];
        try {
            $result = $this->esignService->getDocumentStatus($docId);
            $this->log->info("result status is ".$result);
            return $this->getSuccessResponseWithData(['status' => $result]);
        } catch (Exception $e) {
            $this->log->error("error occured -".$e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
    }
    
}