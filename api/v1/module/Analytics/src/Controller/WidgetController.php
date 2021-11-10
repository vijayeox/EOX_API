<?php

namespace Analytics\Controller;

use Analytics\Model\Widget;
use Analytics\Service\WidgetService;
use Exception;
use Oxzion\Controller\AbstractApiController;

class WidgetController extends AbstractApiController
{
    private $widgetService;

    /**
     * @ignore __construct
     */
    public function __construct(WidgetService $widgetService)
    {
        parent::__construct(null, __class__, Widget::class);
        $this->setIdentifierName('widgetUuid');
        $this->widgetService = $widgetService;
        $this->log = $this->getLogger();
    }

    /**
     * Create Widget API
     * @api
     * @link /analytics/widget
     * @method POST
     * @param array $data Array of elements as shown
     * <code> {
     *               query_id : integer
     *               visualization_id : integer
     *               ispublic : integer(binary)
     *   } </code>
     * @return array Returns a JSON Response with Status Code and Created Widget.
     */
    public function create($data)
    {
        try {
            $generated = $this->widgetService->createWidget($data);
            $data['newWidgetUuid'] = $generated['uuid'];
            return $this->getSuccessResponseWithData($data, 201);
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
    }

    /**
     * Update Widget API
     * @api
     * @link /analytics/widget/:widgetUuid
     * @method PUT
     * @param array $uuid ID of Widget to update
     * @param array $data
     * @return array Returns a JSON Response with Status Code and Created Widget.
     */
    public function update($uuid, $data)
    {
        try {
            $generated = $this->widgetService->updateWidget($uuid, $data);
            $data['version'] = $generated['version'];
            return $this->getSuccessResponseWithData($data, 200);
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
    }

    public function delete($uuid)
    {
        $params = $this->params()->fromQuery();
        try {
            $this->widgetService->deleteWidget($uuid, $params['version']);
            return $this->getSuccessResponse();
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
    }

    /**
     * GET Widget API
     * @api
     * @link /analytics/widget/:widgetUuid
     * @method GET
     * @param array $dataget of Widget
     * @return array $data
     * {
     *              uuid : string,
     *              type : string,
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
        try {
            if ($uuid == 'byName') {
                $result = $this->widgetService->getWidgetByName($params['name']);
            } else {
                $result = $this->widgetService->getWidget($uuid, $params);
            }
            if ($result == 0) {
                return $this->getErrorResponse("Widget not found", 404, ['uuid' => $uuid]);
            }
        } catch (Exception $e) {
            $response = ['data' => $uuid];
            $this->log->error($e->getMessage(), $e);
            return $this->getErrorResponse($e->getMessage(), 500, $response);
        }
        return $this->getSuccessResponseWithData($result);
    }

    /**
     * GET Widget API
     * @api
     * @link /analytics/widget
     * @method GET
     * @param      integer      $limit   (number of rows to fetch)
     * @param      integer      $skip    (number of rows to skip)
     * @param      array[json]  $sort    (sort based on field and dir json)
     * @param      array[json]  $filter  (filter with logic and filters)
     * @return array $dataget list of Datasource
     * <code>status : "success|error",
     *              name : string,
     *              type : string,
     *              created_by: integer,
     *              date_created: date,
     *              org_id: integer,
     *              isdeleted: tinyint
     * </code>
     */
    public function getList()
    {
        $params = $this->params()->fromQuery();
        try {
            if (array_key_exists("filter", $params)) {
                $filterParams = json_decode($params['filter'], true);
                if (array_key_exists("filter", $filterParams[0])) {
                    $filterArray = $filterParams[0]['filter']['filters'];
                    if (!empty($filterArray)) {
                        foreach ($filterArray as $key => $val) {
                            $filterVal = "w." . $val['field'];
                            $filterParams[0]['filter']['filters'][$key]['field'] = $filterVal;
                            $filterParams[0]['filter']['filters'][$key]['operator'] = $val['operator'];
                            $filterParams[0]['filter']['filters'][$key]['value'] = $val['value'];
                            $params['filter'] = json_encode($filterParams);
                        }
                    }
                }
            }
            $result = $this->widgetService->getWidgetList($params);
            if ($result == 0) {
                return $this->getErrorResponse("Widgets not found", 404, ['params' => $params]);
            }
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->getErrorResponse($e->getMessage(), 500);
        }
        return $this->getSuccessResponseWithData($result);
    }

    /**
     * Copy Widget API
     * @api
     * @link /analytics/widget/widgetUuid/copy
     * @method POST
     * @param
     * <code> {
     *
     *   } </code>
     * @return array Returns a JSON Response with Status Code and Created Widget.
     */
    public function copyWidgetAction()
    {
        try {
            $data = $this->extractPostData();
            $params = array_merge($data, $this->params()->fromRoute());
            $result = $this->widgetService->copyWidget($params);
            $data['newWidgetUuid'] = $result['uuid'];
            return $this->getSuccessResponseWithData($data, 201);
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
    }

    /**
     * Preview Widget Template API
     * @api
     * @link /analytics/widget/preiewTemplate
     * @method POST
     * @param array[json] $configuration
     * @param array[json] $queries
     * @param array[json] $expression
     * @return array Returns a JSON Response with Status Code and the HTML for preview
     */

    public function previewWidgetAction()
    {
        $data = $this->params()->fromPost();
        $params = array_merge($data, $this->params()->fromRoute());
        try {
            $result = $this->widgetService->previewWidget($params);
            return $this->getSuccessResponseWithData($result, 200);
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
    }

}
