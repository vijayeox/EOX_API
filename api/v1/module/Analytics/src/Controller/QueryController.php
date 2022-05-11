<?php

namespace Analytics\Controller;

use Analytics\Model\Query;
use Exception;
use Oxzion\Controller\AbstractApiController;
use Oxzion\ValidationException;
use Zend\Db\Exception\ExceptionInterface as ZendDbException;

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
        $this->log = $this->getLogger();
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
            $this->queryService->createQuery($data);
            return $this->getSuccessResponseWithData($data, 201);
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
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
            $this->queryService->updateQuery($uuid, $data);
            return $this->getSuccessResponseWithData($data, 200);
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
    }

    public function delete($uuid)
    {
        $params = $this->params()->fromQuery();
        $version = $params['version'];
        try {
            $this->queryService->deleteQuery($uuid, $version);
            return $this->getSuccessResponse();
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
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
        if ($result == 0) {
            return $this->getErrorResponse("Query not found", 404, ['params' => $params]);
        }
        return $this->getSuccessResponseWithData($result);
    }

    public function previewQueryAction()
    {
        $params = array_merge($this->extractPostData(), $this->params()->fromRoute());
        try {
            $result = $this->queryService->previewQuery($params);
            if (!$result) {
                return $this->getErrorResponse("Query Cannot be executed", 404);
            }
        } catch (ValidationException $e) {
            $response = ['data' => $data, 'errors' => $e->getErrors()];
            return $this->getErrorResponse("Validation Errors", 404, $response);
        } catch (Exception $e) {
            $response = ['data' => $data, 'errors' => 'Query could not be executed'];
            return $this->getErrorResponse("Validation Errors", 404, $response);
        }
        if (isset($result['targetquery'])) {
            $returnresult = ['result' => $result['data'], 'targetquery' => $result['targetquery']];
        } else {
            $returnresult = ['result' => $result['data']];
        }

        return $this->getSuccessResponseWithData($returnresult);
    }

    /**
     * Multiple Query API
     * @api
     * @link /analytics/query/data
     * @method POST
     * @param JSON array of uuids
     * <code> {
     *               "uuids" : ["list of uuids"]
     *   } </code>
     * @return array Returns a JSON Response with Status Code and executed querys result.
     */
    public function queryDataAction()
    {
        $data = $this->extractPostData();
        // print_r($data);exit;
        try {
            $this->log->info("Query Data Action- " . print_r($data, true));
            $result = $this->queryService->queryData($data);
            if (!$result) {
                return $this->getErrorResponse("Querys cannot be executed", 404);
            }
        } catch (ValidationException $e) {
            $this->log->error("Validation Exception- ", $e);
            $response = ['data' => $data, 'errors' => $e->getErrors()];
            return $this->getErrorResponse("Validation Errors", 404, $response);
        } catch (ZendDbException $e) {
            $this->log->error("Zend DB Exception- ", $e);
            $response = ['data' => $data, 'errors' => 'Looks like the server encountered some problem'];
            return $this->getErrorResponse("Internal Error", 500, $response);
        } catch (InvalidInputException $e) {
            $this->log->error("Invalid Input Exception- ", $e);
            $response = ['data' => $data, 'errors' => $e->getMessage()];
            return $this->getErrorResponse("Invalid Input Errors", 404, $response);
        } catch (Exception $e) {
            $this->log->error("Query Data Action Exception- ", $e);
            $response = ['data' => $data, 'errors' => $e->getMessage()];
            return $this->getErrorResponse("Errors", 404, $response);
        }
        return $this->getSuccessResponseWithData(array('result' => $result));
    }
}
