<?php

namespace Oxzion\AppDelegate;

use Logger;
use Oxzion\Utils\RestClient;

class HTTPMethod
{
    const GET = 1;
    const POST = 2;
    const PUT = 3;
    const DELETE = 4;
    const POSTMULTIPART = 5;
    const POSTWITHHEADERS = 6;
}

trait HttpClientTrait
{
    protected $logger;
    private $restClient;

    private function getRestClient()
    {
        if (!$this->restClient) {
            $this->logger = Logger::getLogger(__CLASS__);
            $this->restClient = new RestClient(null);
        }
        return $this->restClient;
    }

    public function makeRequest(
        $type,
        $url,
        $params = array(),
        $headers = array(),
        $fileParams = array()
    ) {
        $this->logger->info("HTTPRequestTrait");
        $restClient = $this->getRestClient();
        switch ($type) {
            case HTTPMethod::GET:
                $response = $restClient->get(
                    $url,
                    $params,
                    $headers
                );
                break;
            case HTTPMethod::POST:
                $response = $restClient->post(
                    $url,
                    $params
                );
                break;
            case HTTPMethod::PUT:
                $response = $restClient->put(
                    $url,
                    $params,
                    $headers
                );
                break;
            case HTTPMethod::DELETE:
                $response = $restClient->delete(
                    $url,
                    $params,
                    $headers
                );
                break;
            case HTTPMethod::POSTMULTIPART:
                $response = $restClient->postMultiPart(
                    $url,
                    $params,
                    $fileParams
                );
                break;
            case HTTPMethod::POSTWITHHEADERS:
                $response = $restClient->postWithHeader(
                    $url,
                    $params,
                    $headers
                );
                break;
            default:
                $response = array();
                break;
        }
        return $response;
    }
}
