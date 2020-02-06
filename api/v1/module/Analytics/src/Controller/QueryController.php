<?php

namespace Analytics\Controller;

use Zend\Log\Logger;
use Analytics\Model\Query;
use Oxzion\Controller\AbstractApiController;
use Oxzion\ValidationException;
use Oxzion\VersionMismatchException;
use Exception;

class QueryController extends AbstractApiController
{

    private $queryService;

    /**
     * @ignore __construct
     */
    public function __construct($queryService)
    {
        parent::__construct(null, __class__, Query::class);
        $this->setIdentifierName('queryUuid');
        $this->queryService = $queryService;
    }

    /**
     * Create Query API
     * @api
     * @link /analytics/query
     * @method POST
     * @param array $data Array of elements as shown
     * <code> {
     *               name : string,
     *               datasource_id : integer,
     *               query_json : string,
     *               ispublic : integer,
     *   } </code>
     * @return array Returns a JSON Response with Status Code and Created Query.
     */
    public function create($data)
    {
        $data = $this->params()->fromPost();
        try {
            $count = $this->queryService->createQuery($data);
        }
        catch (ValidationException $e) {
            $response = ['data' => $data, 'errors' => $e->getErrors()];
            return $this->getErrorResponse("Validation Errors", 404, $response);
        }
        if ($count == 0) {
            return $this->getFailureResponse("Failed to create a new entity", $data);
        }
        return $this->getSuccessResponseWithData($data, 201);
    }

    /**
     * Update Query API
     * @api
     * @link /analytics/query/:queryUuid
     * @method PUT
     * @param array $uuid ID of Query to update
     * @param array $data
     * @return array Returns a JSON Response with Status Code and Created Query.
     */
    public function update($uuid, $data)
    {
        try {
            $count = $this->queryService->updateQuery($uuid, $data);
        }
        catch (ValidationException $e) {
            $response = ['data' => $data, 'errors' => $e->getErrors()];
            return $this->getErrorResponse("Validation Errors", 404, $response);
        }
        catch (VersionMismatchException $e) {
            return $this->getErrorResponse('Version changed', 404, ['reason' => 'Version changed', 'reasonCode' => 'VERSION_CHANGED', 'new record' => $e->getReturnObject()]);
        }
        if ($count == 0) {
            return $this->getErrorResponse("Query not found for uuid - $uuid", 404);
        }
        return $this->getSuccessResponseWithData($data, 200);
    }

    public function delete($uuid) {
        $params = $this->params()->fromQuery();
        if(isset($params['version'])){
            try {
                $response = $this->queryService->deleteQuery($uuid, $params['version']);
            }
            catch (VersionMismatchException $e) {
                return $this->getErrorResponse('Version changed', 404, ['reason' => 'Version changed', 'reasonCode' => 'VERSION_CHANGED',  'new record' => $e->getReturnObject()]);
            }
            if ($response == 0) {
                return $this->getErrorResponse("Query not found for uuid - $uuid", 404, ['uuid' => $uuid]);
            }
            return $this->getSuccessResponse();
        } else {
            return $this->getErrorResponse("Deleting without version number is not allowed. Use */delete?version=<version> URL.", 404, ['uuid' => $uuid]);
        }
    }

    /**
     * GET Query API
     * @api
     * @link /analytics/query/:queryUuid
     * @method GET
     * @param array $dataget of Query
     * @return array $data
     * {
     *              id: integer,
     *              uuid: string,
     *              name : string,
     *              datasource_id : integer,
     *              query_json : string,
     *              ispublic : integer,
     *              created_by: integer,
     *              date_created: date,
     *              org_id: integer,
     *              isdeleted: tinyint
     * }
     * @return array Returns a JSON Response with Status Code and Created Group.
     */
    public function get($uuid)
    {
        $params = $this->params()->fromQuery();
        $result = $this->queryService->getQuery($uuid, $params);
        if ($result == 0) {
            return $this->getErrorResponse("Query not found", 404, ['uuid' => $uuid]);
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
     *              id: integer,
     *              uuid: string,
     *              name : string,
     *              datasource_id : integer,
     *              query_json : string,
     *              ispublic : integer,
     *              created_by: integer,
     *              date_created: date,
     *              org_id: integer,
     *              isdeleted: tinyint
     * </code>
     */
    public function getList()
    {
        $params = $this->params()->fromQuery();
        $result = $this->queryService->getQueryList($params);
        return $this->getSuccessResponseWithData($result);
    }

    public function previewQueryAction()
    {
        $data = $this->params()->fromPost();
        $params = array_merge($data, $this->params()->fromRoute());
        try{
            $result = $this->queryService->previewQuery($params);
            if (!$result) {
                return $this->getErrorResponse("Query Cannot be executed", 404);
            }
        }
        catch(ValidationException $e) {
            $response = ['data' => $data, 'errors' => $e->getErrors()];
            return $this->getErrorResponse("Validation Errors", 404, $response);
        }
        catch(Exception $e) {
            $response = ['data' => $data, 'errors' => 'Query could not be executed'];
            return $this->getErrorResponse("Validation Errors", 404, $response);
        }
        return $this->getSuccessResponseWithData(array('result' => $result));
    }
}
