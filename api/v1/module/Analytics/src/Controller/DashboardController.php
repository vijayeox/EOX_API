<?php

namespace Analytics\Controller;

use Analytics\Model\Dashboard;
use Exception;
use Oxzion\Controller\AbstractApiController;

class DashboardController extends AbstractApiController
{
    private $dashboardService;

    /**
     * @ignore __construct
     */
    public function __construct($dashboardService)
    {
        parent::__construct(null, __class__, Dashboard::class);
        $this->setIdentifierName('dashboardUuid');
        $this->dashboardService = $dashboardService;
    }

    /**
     * Create Dashboard API
     * @api
     * @link /analytics/dashboard
     * @method POST
     * @param array $data Array of elements as shown
     * <code> {
     *               name : string
     *               dashboard_type : string
     *               ispublic : integer(binary)
     *               description : string
     *   } </code>
     * @return array Returns a JSON Response with Status Code and Created Dashboard.
     */
    public function create($data)
    {
        try {
            $returnData = $this->dashboardService->createDashboard($data);
            array_merge($data, $returnData);
            return $this->getSuccessResponseWithData($data, 201);
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
    }

    /**
     * Update Dashboard API
     * @api
     * @link /analytics/dashboard/:dashboardUuid
     * @method PUT
     * @param array $uuid ID of Dashboard to update
     * @param array $data
     * @return array Returns a JSON Response with Status Code and Created Dashboard.
     */
    public function update($uuid, $data)
    {
        try {
            $result = $this->dashboardService->updateDashboard($uuid, $data);
            return $this->getSuccessResponseWithData($result, 200);
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
    }

    public function delete($uuid)
    {
        $params = $this->params()->fromQuery();
        try {
            $this->dashboardService->deleteDashboard($uuid, $params['version']);
            return $this->getSuccessResponse();
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
    }

    /**
     * GET Dashboard API
     * @api
     * @link /analytics/dashboard/:dashboardUuid
     * @method GET
     * @param array $dataget of Dashboard
     * @return array $data
     * {
     *              id: integer,
     *              uuid : string,
     *              name : string,
     *              ispublic : integer,
     *              description : string,
     *              dashboard_type : string,
     *              created_by: integer,
     *              date_created: date,
     *              org_id: integer,
     *              isdeleted: tinyint
     *   }
     * @return array Returns a JSON Response with Status Code and Created Group.
     */
    public function get($uuid)
    {
        $result = $this->dashboardService->getDashboard($uuid);
        if ($result == 0) {
            return $this->getErrorResponse("Dashboard not found", 404, ['uuid' => $uuid]);
        }
        return $this->getSuccessResponseWithData($result);
    }

    /**
     * GET Dashboard API
     * @api
     * @link /analytics/dashboard
     * @method GET
     * @param      integer      $limit   (number of rows to fetch)
     * @param      integer      $skip    (number of rows to skip)
     * @param      array[json]  $sort    (sort based on field and dir json)
     * @param      array[json]  $filter  (filter with logic and filters)
     * @return array $dataget list of Datasource
     * <code>status : "success|error",
     *              id: integer,
     *              uuid : string,
     *              name : string,
     *              ispublic : integer,
     *              description : string,
     *              dashboard_type : string,
     *              created_by: integer,
     *              date_created: date,
     *              org_id: integer,
     *              isdeleted: tinyint
     * </code>
     */
    public function getList()
    {
        $params = $this->params()->fromQuery();
        $result = $this->dashboardService->getDashboardList($params);
        return $this->getSuccessResponseDataWithPagination($result['data'], $result['total']);
    }
}
