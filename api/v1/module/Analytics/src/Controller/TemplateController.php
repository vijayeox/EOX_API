<?php

namespace Analytics\Controller;

use Analytics\Service\TemplateService;
use Exception;
use Oxzion\Controller\AbstractApiController;

class TemplateController extends AbstractApiController
{
    private $templateService;

    /**
     * @ignore __construct
     */
    public function __construct(TemplateService $templateService)
    {
        parent::__construct(null, __class__, Template::class);
        $this->setIdentifierName('templateName');
        $this->templateService = $templateService;
        $this->log = $this->getLogger();
    }

    /**
     * Create Template API
     * @api
     * @link /analytics/Template
     * @method POST
     * @param array $data Array of elements as shown
     * <code> {
     *               name : string
     *               content : string
     *   } </code>
     * @return array Returns a JSON Response with Status Code and Created Template.
     */
    public function create($data)
    {
        $this->log->info(__CLASS__ . "-> Create Template - " . print_r($data, true));
        $data = $this->extractPostData();
        try {
            $generated = $this->templateService->createTemplate($data);
            if ($generated == 1) {
                return $this->getErrorResponse("Template name does not exist", 404);
            } else if ($generated == 0) {
                return $this->getErrorResponse("File Name is empty", 500);
            }
            return $this->getSuccessResponseWithData($generated, 201);
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
    }

    /**
     * Update Template API
     * @api
     * @link /analytics/Template/:TemplateUuid
     * @method PUT
     * @param array $uuid ID of Template to update
     * @param array $data
     * @return array Returns a JSON Response with Status Code and Created Template.
     */
    public function update($uuid, $data)
    {
        try {
            $version = $this->TemplateService->updateTemplate($uuid, $data);
            return $this->getSuccessResponseWithData(['version' => $version], 200);
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
    }

    public function delete($uuid)
    {
        $params = $this->params()->fromQuery();
        try {
            $this->TemplateService->deleteTemplate($uuid, $params['version']);
            return $this->getSuccessResponse();
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
    }

    /**
     * GET Template API
     * @api
     * @link /analytics/Template/:TemplateName
     * @method GET
     * @param array $dataget of Template
     * @return array $data
     * {
     *              uuid : string,
     *              type : string,
     *              created_by: integer,
     *              date_created: date,
     *              account_id: integer,
     *              isdeleted: tinyint
     *   }
     * @return array Returns a JSON Response with Status Code and Created Group.
     */
    public function get($templateName)
    {
        $result = 0;
        $result = $this->templateService->getTemplate($templateName);
        // print_r($result);exit;
        if ($result === 0) {
            return $this->getErrorResponse("Template not found", 404, ['templateName' => $templateName]);
        }
        return $this->getSuccessResponseWithData($result);
    }

    /**
     * GET Template API
     * @api
     * @link /analytics/Template
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
     *              account_id: integer,
     *              isdeleted: tinyint
     * </code>
     */
    public function getList()
    {
        $params = $this->params()->fromQuery();
        $this->log->info(__CLASS__ . "-> Get Template list - " . json_encode($params, true));
        try {
            $result = $this->templateService->getTemplateList($params);
            return $this->getSuccessResponseWithData($result, 201);
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
    }
}
