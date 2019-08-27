<?php

namespace Analytics\Controller;

use Zend\Log\Logger;
use Analytics\Model\WidgetTable;
use Analytics\Model\Widget;
use Analytics\Service\WidgetService;
use Oxzion\Controller\AbstractApiController;
use Zend\Db\Adapter\AdapterInterface;
use Oxzion\ValidationException;
use Zend\InputFilter\Input;


class WidgetController extends AbstractApiController
{

    private $widgetService;

    /**
     * @ignore __construct
     */
    public function __construct(WidgetTable $table, WidgetService $widgetService, Logger $log, AdapterInterface $dbAdapter)
    {
        parent::__construct($table, $log, __class__, Widget::class);
        $this->setIdentifierName('widgetUuid');
        $this->widgetService = $widgetService;
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
        $data = $this->params()->fromPost();
        try {
            $count = $this->widgetService->createWidget($data);
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
            $count = $this->widgetService->updateWidget($uuid, $data);
        } catch (ValidationException $e) {
            $response = ['data' => $data, 'errors' => $e->getErrors()];
            return $this->getErrorResponse("Validation Errors", 404, $response);
        }
        if ($count == 0) {
            return $this->getErrorResponse("Widget not found for uuid - $uuid", 404);
        }
        return $this->getSuccessResponseWithData($data, 200);
    }

    /**
     * Delete Widget API
     * @api
     * @link /analytics/widget/:widgetUuid
     * @method DELETE
     * @param $uuid ID of Widget to Delete
     * @return array success|failure response
     */
    public function delete($uuid)
    {
        $response = $this->widgetService->deleteWidget($uuid);
        if ($response == 0) {
            return $this->getErrorResponse("Widget not found for uuid - $uuid", 404, ['uuid' => $uuid]);
        }
        return $this->getSuccessResponse();
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
        $result = $this->widgetService->getWidget($uuid,$params);
        if ($result == 0) {
            return $this->getErrorResponse("Widget not found", 404, ['uuid' => $uuid]);
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
        $result = $this->widgetService->getWidgetList($params);
        if ($result == 0) {
            return $this->getErrorResponse("No records found",404);
        }
        return $this->getSuccessResponseWithData($result);
    }
}

