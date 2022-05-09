<?php

namespace Rate\Controller;

use Exception;
use Rate\Model\RateCondition;
use Oxzion\Controller\AbstractApiController;

class RateConditionController extends AbstractApiController
{
    private $rateConditionService;

    /**
     * @ignore __construct
     */
    public function __construct($rateConditionService)
    {
        parent::__construct(null, __class__, RateCondition::class);
        $this->setIdentifierName('conditionUuid');
        $this->rateConditionService = $rateConditionService;
    }

    /**
     * Create Rate Condition API
     * @api
     * @link /ratecondition
     * @method POST
     * @param array $data Array of elements as shown
     * <code> {
     *               name : string,
     *               value: string,
     *               sequence_id: int,
     *               entity_id: string,
     *               account_id: string
     *               app_id: string
     *   } </code>
     * @return array Returns a JSON Response with Status Code and Created Rate Condition.
     */
    public function create($data)
    {
        $data = $this->params()->fromPost();
        try {
            $this->rateConditionService->createRateCondition($data);
            return $this->getSuccessResponseWithData($data, 201);
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
    }

    /**
     * Update Rate Condition API
     * @api
     * @link /ratecondition/:conditionUuid
     * @method PUT
     * @param array $uuid ID of rate condition to update
     * @param array $data
     * @return array Returns a JSON Response with Status Code and Updated Rate Condition.
     */
    public function update($uuid, $data)
    {
        try {
            $this->rateConditionService->updateRateCondition($uuid, $data);
            return $this->getSuccessResponseWithData($data, 200);
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
    }

    /**
     * Delete Rate Condition API
     * @api
     * @link /ratecondition/:conditionUuid?version=:versionNumber
     * @method DELETE
     * @param array $uuid ID of rate condition to Delete
     * @param array $data
     * @return array Returns a JSON Response with Status Code and Deleted Rate Condition.
     */
    public function delete($uuid)
    {
        $params = $this->params()->fromQuery();
        try {
            $this->rateConditionService->deleteRateCondition($uuid, $params['version']);
            return $this->getSuccessResponse();
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
    }

    /**
     * GET Rate Condition API
     * @api
     * @link /ratecondition/:conditionUuid
     * @method GET
     * @param array $dataget of Rate
     * @return array $data
     * {
     *              uuid : string,
     *              name : string,
     *              value: string,
     *              sequence_id: int,
     *              version: integer,
     *              created_by: integer,
     *              date_created: date,
     *              account_id: string,
     *              app_id: string,
     *              entity_id: string,
     *              isdeleted: tinyint
     *   }
     * @return array Returns a JSON Response with Status Code and fetched Rate condition data.
     */
    public function get($uuid)
    {
        $result = $this->rateConditionService->getRateCondition($uuid);
        if ($result == 0) {
            return $this->getErrorResponse("Rate Condition not found", 404, ['uuid' => $uuid]);
        }
        return $this->getSuccessResponseWithData($result);
    }

    /**
     * GET Rate Condition API
     * @api
     * @link /ratecondition
     * @method GET
     * @param      integer      $limit   (number of rows to fetch)
     * @param      integer      $skip    (number of rows to skip)
     * @param      array[json]  $sort    (sort based on field and dir json)
     * @param      array[json]  $filter  (filter with logic and filters)
     * @return array $dataget list of Datasource
     * <code>status : "success|error",
     *              uuid : string,
     *              name : string,
     *              value: string,
     *              sequence_id: int,
     *              version: integer,
     *              created_by: integer,
     *              date_created: date,
     *              account_id: string,
     *              app_id: string,
     *              entity_id: string,
     *              isdeleted: tinyint
     * </code>
     */
    public function getList()
    {
        $params = $this->params()->fromQuery();
        try{
            $result = $this->rateConditionService->getRateConditionList($params);
            return $this->getSuccessResponseWithData($result);
        }
        catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
    }
}
