<?php
namespace Ims\Controller;

use Ims\Controller\AbstractController;
use Oxzion\Insurance\Ims\Service as ImsService;

class ProducerController extends AbstractController
{
    protected $imsService;
    public function __construct(ImsService $imsService)
    {
        parent::__construct($imsService, 'ProducerFunctions');
        $this->imsService = $imsService;
    }

    /**
     * Create Producer Functions
     * The common function to create all the apis for producer, this includes producer, contact and Location as well
     * @api
     * @link /ims/createProducer[/:operation]
     * @method POST
     * @param List of all the fields that are mentioned in the IMS API. Required fields are also mentioned there
     * @see  https://ws2.mgasystems.com/ims_demo/ProducerFunctions.asmx
     * @param $data
     * @return array Returns a JSON Response with Status Code and Created IMS Producer.
     */
    public function createProducerAction()
    {
        $params = $this->params()->fromRoute(); // This will capture the operation that we are going to perform
        $data = $this->extractPostData(); //This will extract the POST parameters
        try {
            $this->log->info(__CLASS__ . "-> Add Producer - " . print_r($params, true) . "\n Data: " . print_r($data, true));
            $response = $this->imsService->createAPI($params['operation'], $data);
        } catch (\Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
        return $this->getSuccessResponseWithData($response, 201);
    }

    /**
     * Get Producer Functions
     * The common function to get all the Producer information , this includes producer, contact and Location as well
     * @api
     * @link /ims/getProducer[/:operation]
     * @method POST
     * @param $ List of all the fields that are mentioned in the IMS API. Required fields are also mentioned there
     * @see  https://ws2.mgasystems.com/ims_demo/ProducerFunctions.asmx
     * @param $data
     * @return array Returns a JSON Response with Status Code and Producer and their details. This depends on which api we are referring to.
     */
    public function getProducerAction()
    {
        $params = $this->params()->fromRoute(); // This will capture the operation that we are going to perform
        $data = $this->extractPostData(); //This will extract the POST parameters
        try {
            $this->log->info(__CLASS__ . "-> Get Producer - " . print_r($params, true) . "\n Data: " . print_r($data, true));
            $response = $this->imsService->getAPI($params['operation'], $data);
        } catch (\Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
        return $this->getSuccessResponseWithData($response, 201);
    }

}