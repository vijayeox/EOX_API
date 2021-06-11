<?php
namespace Ims\Controller;

use Ims\Controller\AbstractController;
use Oxzion\Insurance\Ims\Service as ImsService;

class QuoteController extends AbstractController
{
    public function __construct(ImsService $imsService)
    {
        parent::__construct($imsService, 'QuoteFunctions');
    }

    /**
     * Create Quote Functions
     * The common function to create all the apis for Quote, this includes Quote and submission
     * @api
     * @link /ims/createQuote[/:operation]
     * @method POST
     * @param List of all the fields that are mentioned in the IMS API. Required fields are also mentioned there
     * @see  https://ws2.mgasystems.com/ims_demo/QuoteFunctions.asmx
     * @param $data
     * @return array Returns a JSON Response with Status Code and Created IMS Quote.
     */
    public function createQuoteAction()
    {
        $params = $this->params()->fromRoute(); // This will capture the operation that we are going to perform
        $data = $this->extractPostData(); //This will extract the POST parameters
        try {
            $this->log->info(__CLASS__ . "-> Create Quote - " . print_r($params, true) . "\n Data: " . print_r($data, true));
            $response = $this->imsService->createAPI($params, $data);
        } catch (\Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
        return $this->getSuccessResponseWithData($response, 201);
    }

    /**
     * Get Quote Functions
     * The common function to create all the apis for Quote, this includes Quote and submission
     * @api
     * @link /ims/getQuote[/:operation]
     * @method POST
     * @param List of all the fields that are mentioned in the IMS API. Required fields are also mentioned there
     * @see  https://ws2.mgasystems.com/ims_demo/QuoteFunctions.asmx
     * @param $data
     * @return array Returns a JSON Response with Status Code and Created IMS Quote.
     */
    public function getQuoteAction()
    {
        $params = $this->params()->fromRoute(); // This will capture the operation that we are going to perform
        $data = $this->extractPostData(); //This will extract the POST parameters
        try {
            $this->log->info(__CLASS__ . "-> Get Quote - " . print_r($params, true) . "\n Data: " . print_r($data, true));
            $response = $this->imsService->getAPI($params, $data);
        } catch (\Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
        return $this->getSuccessResponseWithData($response, 201);
    }

}