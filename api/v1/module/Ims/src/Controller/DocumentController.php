<?php
namespace Ims\Controller;

use Ims\Controller\AbstractController;
use Oxzion\Insurance\Ims\Service as ImsService;

class DocumentController extends AbstractController
{
    public function __construct(ImsService $imsService)
    {
        parent::__construct($imsService, 'DocumentFunctions');
    }

    /**
     * Create Document Functions
     * The common function to create all the apis for Document, this includes Document and submission
     * @api
     * @link /ims/createDocument[/:operation]
     * @method POST
     * @param List of all the fields that are mentioned in the IMS API. Required fields are also mentioned there
     * @see  https://ws2.mgasystems.com/ims_demo/DocumentFunctions.asmx
     * @param $data
     * @return array Returns a JSON Response with Status Code and Created IMS Document.
     */
    public function createDocumentAction()
    {
        $params = $this->params()->fromRoute(); // This will capture the operation that we are going to perform
        $data = $this->extractPostData(); //This will extract the POST parameters
        try {
            $this->log->info(__CLASS__ . "-> Document Function - " . print_r($params, true) . "\n Data: " . print_r($data, true));
            $response = $this->imsService->createAPI($params, $data);
        } catch (\Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
        return $this->getSuccessResponseWithData($response, 201);
    }

    /**
     * Get Document Functions
     * The common function to create all the apis for Document, this includes Document and submission
     * @api
     * @link /ims/createDocument[/:operation]
     * @method POST
     * @param List of all the fields that are mentioned in the IMS API. Required fields are also mentioned there
     * @see  https://ws2.mgasystems.com/ims_demo/DocumentFunctions.asmx
     * @param $data
     * @return array Returns a JSON Response with Status Code and Created IMS Document.
     */
    public function getDocumentAction()
    {
        $params = $this->params()->fromRoute(); // This will capture the operation that we are going to perform
        $data = $this->extractPostData(); //This will extract the POST parameters
        try {
            $this->log->info(__CLASS__ . "-> Document Function - " . print_r($params, true) . "\n Data: " . print_r($data, true));
            $response = $this->imsService->getAPI($params, $data);
        } catch (\Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
        return $this->getSuccessResponseWithData($response, 201);
    }

}