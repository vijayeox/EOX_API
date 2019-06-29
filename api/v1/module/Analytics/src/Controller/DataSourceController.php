<?php

namespace Analytics\Controller;

use Zend\Log\Logger;
use Analytics\Model\DataSourceTable;
use Analytics\Model\DataSource;
use Analytics\Service\DataSourceService;
use Oxzion\Controller\AbstractApiController;
use Zend\Db\Adapter\AdapterInterface;
use Oxzion\ValidationException;
use Zend\InputFilter\Input;


class DataSourceController extends AbstractApiController
{

    private $dataSourceService;

    /**
     * @ignore __construct
     */
    public function __construct(DataSourceTable $table, DataSourceService $dataSourceService, Logger $log, AdapterInterface $dbAdapter)
    {
        parent::__construct($table, $log, __class__, DataSource::class);
        $this->setIdentifierName('dataSourceId');
        $this->dataSourceService = $dataSourceService;
    }

    /**
     * Create DataSource API
     * @api
     * @link /analytics/dataSource
     * @method POST
     * @param array $data Array of elements as shown
     * <code> {
     *               name : string,
     *               type : string,
     *               connection_string : string
     *   } </code>
     * @return array Returns a JSON Response with Status Code and Created DataSource.
     */
    public function create($data)
    {
        $data = $this->params()->fromPost();
        try {
            $count = $this->dataSourceService->createDataSource($data);
        } catch (ValidationException $e) {
            $response = ['data' => $data, 'errors' => $e->getErrors()];
            return $this->getErrorResponse("Validation Errors", 404, $response);
        }
        if ($count == 0) {
            return $this->getFailureResponse("Failed to create a new entity", $data);
        }
        return $this->getSuccessResponseWithData($data, 201);
    }

    /**
     * Update DataSource API
     * @api
     * @link /analytics/dataSource/:dataSourceId
     * @method PUT
     * @param array $id ID of DataSource to update
     * @param array $data
     * @return array Returns a JSON Response with Status Code and Created DataSource.
     */
    public function update($id, $data)
    {
        try {
            $count = $this->dataSourceService->updateDataSource($id, $data);
        } catch (ValidationException $e) {
            $response = ['data' => $data, 'errors' => $e->getErrors()];
            return $this->getErrorResponse("Validation Errors", 404, $response);
        }
        if ($count == 0) {
            return $this->getErrorResponse("DataSource not found for id - $id", 404);
        }
        return $this->getSuccessResponseWithData($data, 200);
    }

    /**
     * Delete DataSource API
     * @api
     * @link /analytics/dataSource/:dataSourceId
     * @method DELETE
     * @param $id ID of DataSource to Delete
     * @return array success|failure response
     */
    public function delete($id)
    {
        $response = $this->dataSourceService->deleteDataSource($id);
        if ($response == 0) {
            return $this->getErrorResponse("DataSource not found for id - $id", 404, ['id' => $id]);
        }
        return $this->getSuccessResponse();
    }

    /**
     * GET DataSource API
     * @api
     * @link /analytics/datasource[/:dataSourceId]
     * @method GET
     * @param array $dataget of DataSource
     * @return array $data
     * {
     *              id: integer
     *              name : string,
     *              type : string,
     *              connection_string : string
     *              created_by: integer
     *              date_created: date
     *   }
     * @return array Returns a JSON Response with Status Code and Created Group.
     */
    public function get($id)
    {
        $result = $this->dataSourceService->getDataSource($id);
        if ($result == 0) {
            return $this->getErrorResponse("DataSource not found", 404, ['id' => $id]);
        }
        return $this->getSuccessResponseWithData($result);
    }

    /**
     * GET DataSource API
     * @api
     * @link /analytics/datasource
     * @method GET
     * @param      integer      $limit   (number of rows to fetch)
     * @param      integer      $skip    (number of rows to skip)
     * @param      array[json]  $sort    (sort based on field and dir json)
     * @param      array[json]  $filter  (filter with logic and filters)
     * @return array $dataget list of Datasource
     * <code>status : "success|error",
     *              id: integer
     *              name : string,
     *              type : string,
     *              connection_string : string
     *              created_by: integer
     *              date_created: date
     * </code>
     */
    public function getList()
    {
        $params = $this->params()->fromQuery();
        $result = $this->dataSourceService->getDataSourceList($params);
        if ($result == 0) {
            return $this->getErrorResponse("No records found",404);
        }
        return $this->getSuccessResponseWithData($result);
    }
}

