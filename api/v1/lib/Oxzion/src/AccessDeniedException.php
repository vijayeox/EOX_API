<?php

namespace Oxzion;

class AccessDeniedException extends OxServiceException
{
    public function __construct($message)
    {
        parent::__construct($message, null, OxServiceException::ERR_CODE_UNAUTHORIZED);
    }
    
    public function getErrors()
    {
        return $this->errors;
    }
}