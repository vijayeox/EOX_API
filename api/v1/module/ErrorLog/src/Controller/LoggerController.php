<?php

namespace ErrorLog\Controller;

use Oxzion\Controller\AbstractApiController;

class LoggerController extends AbstractApiController
{
    /**
    * @ignore __construct
    */
    public function __construct()
    {
        $this->log = $this->getLogger();
        $this->logClass = get_class($this);
    }

    public function create($data)
    {
        try {
	        switch (strtolower($data['level'])) {
	            case 'trace':
	            case 'debug':
	            case 'info':
	            case 'warn':
	            case 'error':
	            case 'fatal':
	                break;
	            default:
	                $data['level'] = 'info';
	                break;
	        }
            $this->log->{strtolower($data['level'])}(print_r($data['message'], true));
            return $this->getSuccessResponseWithData($data);
        } catch (\Exception $e) {
            $response = ['data' => $data, 'errors' => $e->getMessage()];
            return $this->getErrorResponse("Errors", 400, $response);
        }
    }

}