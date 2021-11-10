<?php

namespace Oxzion\Workflow\Camunda;
use Oxzion\OxServiceException;
use Throwable;

class WorkflowException extends OxServiceException{
    private $reason;

    public function __construct($message, $reason, $contextData = NULL,
            int $errorCode = parent::ERR_CODE_INTERNAL_SERVER_ERROR, 
            string $errorType = parent::ERR_TYPE_ERROR, 
            Throwable $rootCause = NULL){
        $this->reason = $reason;
        parent::__construct($message, $contextData, 
                empty($errorCode) ? parent::ERR_CODE_INTERNAL_SERVER_ERROR : $errorCode, 
                empty($errorType) ? parent::ERR_TYPE_ERROR : $errorType,
                $rootCause);
    }

    public function getReason(){
        return $this->reason;
    }
}

?>