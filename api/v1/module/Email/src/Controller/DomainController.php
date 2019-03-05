<?php

namespace Email\Controller;

use Zend\Log\Logger;
use Oxzion\Controller\AbstractApiController;
use Email\Model\DomainTable;
use Email\Model\Domain;
use Email\Service\DomainService;
use Zend\Db\Adapter\AdapterInterface;
use Bos\ValidationException;
use Bos\Auth\AuthContext;
use Bos\Auth\AuthConstants;

class DomainController extends AbstractApiController
{
    /**
     * @var DomainService Instance of Domain Service
     */
    private $domainService;

    /**
     * @ignore __construct
     */
    public function __construct(DomainTable $table, DomainService $domainService, Logger $log, AdapterInterface $dbAdapter)
    {
        parent::__construct($table, $log, __CLASS__, Domain::class);
        $this->setIdentifierName('domainId');
        $this->domainService = $domainService;
    }

    public function create($data)
    {
        $data = $this->params()->fromPost();
        try {
            $count = $this->domainService->createDomain($data);
        } catch (ValidationException $e) {
            $response = ['data' => $data, 'errors' => $e->getErrors()];
            return $this->getErrorResponse("Validation Errors", 404, $response);
        }
        if ($count == 0) {
            return $this->getFailureResponse("Failed to create a new entity", $data);
        }
        unset($data['password']);
        return $this->getSuccessResponseWithData($data, 201);
    }


    /**
     * Update Domain API
     * @api
     * @link /domain[/:domainId]
     * @method PUT
     * @param array $id ID of Domain to update
     * @param array $data
     * @return array Returns a JSON Response with Status Code and Created Domain.
     */
    public function update($id, $data)
    {
        try {
            $count = $this->domainService->updateDomain($id, $data);
        } catch (ValidationException $e) {
            $response = ['data' => $data, 'errors' => $e->getErrors()];
            return $this->getErrorResponse("Validation Errors", 404, $response);
        }
        if ($count == 0) {
            return $this->getErrorResponse("Entity not found for id - $id", 404);
        }
        return $this->getSuccessResponseWithData($data, 200);
    }

}