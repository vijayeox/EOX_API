<?php

namespace Rate\Controller;

use Rate\Model\Rate;
use Exception;
use Oxzion\Controller\AbstractApiController;

class RateController extends AbstractApiController
{
    private $rateService;

    /**
     * @ignore __construct
     */
    public function __construct($rateService)
    {
        parent::__construct(null, __class__, Rate::class);
        $this->setIdentifierName('rateUuid');
        $this->rateService = $rateService;
        $this->log = $this->getLogger();
    }

    /**
     * Create Rate API
     * @api
     * @link /rate
     * @method POST
     * @param array $data Array of elements as shown
     * <code> {
     *               name : string,
     *               condition_1 : integer,
     *               condition_2 : integer,
     *               condition_3 : integer,
     *               condition_4 : integer,
     *               condition_5 : integer,
     *               condition_6 : integer,
     *               conditional_expression: string,
     *               value : string,
     *               entity_id : uuid
     *               app_id : uuid
     *               account_id : uuid
     *   } </code>
     * @return array Returns a JSON Response with Status Code and Created Rate.
     */
    public function create($data)
    {
        $data = $this->params()->fromPost();
        try {
            $this->rateService->createRate($data);
            return $this->getSuccessResponseWithData($data, 201);
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
    }

    /**
     * Update Rate  API
     * @api
     * @link /rate/:rateUuid
     * @method PUT
     * @param array $uuid ID of rate  to update
     * @param array $data
     * @return array Returns a JSON Response with Status Code and Updated Rate .
     */
    public function update($uuid, $data)
    {
        try {
            $this->rateService->updateRate($uuid, $data);
            return $this->getSuccessResponseWithData($data, 200);
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
    }

    /**
     * Delete Rate API
     * @api
     * @link /rate/:rateUuid?version=:versionNumber
     * @method DELETE
     * @param array $uuid ID of rate to Delete
     * @param array $data
     * @return array Returns a JSON Response with Status Code and Deleted Rate .
     */
    public function delete($uuid)
    {
        $params = $this->params()->fromQuery();
        $version = $params['version'];
        try {
            $this->rateService->deleteRate($uuid, $version);
            return $this->getSuccessResponse();
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
    }

    /**
     * GET Rate API
     * @api
     * @link /rate/:rateUuid
     * @method GET
     * @param array $dataget of Rate
     * @return array $data
     * {
     *              uuid : string,
     *              name : string,
     *              condition_1: string,
     *              condition_2: string,
     *              condition_3: string,
     *              condition_4: string,
     *              condition_5: string,
     *              condition_6: string,
     *              conditional_expression: string,
     *              value: string,
     *              version: integer,
     *              created_by: integer,
     *              modified_by: integer,
     *              date_created: date,
     *              date_modified: date,
     *              account_id: string,
     *              app_id: string,
     *              entity_id: string,
     *              isdeleted: tinyint
     *   }
     * @return array Returns a JSON Response with Status Code and fetched Rate data.
     */
    public function get($uuid)
    {
        $params = $this->params()->fromQuery();
        $result = $this->rateService->getRate($uuid, $params);
        if ($result == 0) {
            return $this->getErrorResponse("Rate not found", 404, ['uuid' => $uuid]);
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
        $result = $this->rateService->getRateList($params);
        if ($result == 0) {
            return $this->getErrorResponse("Query not found", 404, ['params' => $params]);
        }
        return $this->getSuccessResponseWithData($result);
    }

}
