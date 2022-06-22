<?php
namespace App\Controller;

/**
 * Field Api
 */
use Exception;
use Oxzion\Controller\AbstractApiController;
use Oxzion\Model\Field;
use Oxzion\Model\FieldTable;
use Oxzion\ServiceException;
use Oxzion\Service\FieldService;
use Oxzion\ValidationException;
use Zend\Db\Adapter\AdapterInterface;

/**
 * Field Controller
 */
class FieldController extends AbstractApiController
{
    /**
     * @ignore fieldService
     */
    private $fieldService;
    /**
     * @ignore __construct
     */
    public function __construct(FieldTable $table, FieldService $fieldService, AdapterInterface $dbAdapter)
    {
        parent::__construct($table, Field::class);
        $this->setIdentifierName('id');
        $this->fieldService = $fieldService;
    }
    /**
     * Create Field API
     * @api
     * @link /field
     * @method POST
     * @param array $data Array of elements as shown
     * <code> {
     *               id : integer,
     *               name : string,
     *               formid : integer,
     *   } </code>
     * @return array Returns a JSON Response with Status Code and Created Field.
     */
    public function create($data)
    {
        $appId = $this->params()->fromRoute()['appId'];
        $this->log->info(__CLASS__ . "-> \n Create Field - " . print_r($data, true) . "AppID - ". $appId);
        try {
            $count = $this->fieldService->saveField($appId, $data);
            if ($count == 0) {
                return $this->getFailureResponse("Failed to create a new entity", $data);
            }
        } catch (ValidationException $e) {
            $response = ['data' => $data, 'errors' => $e->getErrors()];
            return $this->getErrorResponse("Validation Errors", 404, $response);
        } catch (Exception $e) {
            $this->log->error(":Error -" . $e->getMessage(), $e);
            $response = ['data' => $data];
            return $this->getErrorResponse($e->getMessage(), 500, $response);
        }
        return $this->getSuccessResponseWithData($data, 201);
    }

    /**
     * GET List Fields API
     * @api
     * @link /field
     * @method GET
     * @return array Returns a JSON Response list of fields based on Form id.
     */
    public function getList()
    {
        $appId = $this->params()->fromRoute()['appId'];
        $result = $this->fieldService->getFields($appId);
        return $this->getSuccessResponseWithData($result['data']);
    }
    /**
     * Update Field API
     * @api
     * @link /field[/:fieldId]
     * @method PUT
     * @param array $id ID of Field to update
     * @param array $data
     * @return array Returns a JSON Response with Status Code and Created Field.
     */
    public function update($id, $data)
    {
        try {
            $count = $this->fieldService->updateField($id, $data);
        } catch (ValidationException $e) {
            $response = ['data' => $data, 'errors' => $e->getErrors()];
            return $this->getErrorResponse("Validation Errors", 404, $response);
        }
        if ($count == 0) {
            return $this->getErrorResponse("Entity not found for id - $id", 404);
        }
        return $this->getSuccessResponseWithData($data, 200);
    }
    /**
     * Delete Field API
     * @api
     * @link /field[/:fieldId]
     * @method DELETE
     * @param $id ID of Field to Delete
     * @return array success|failure response
     */
    public function delete($id)
    {
        $appId = $this->params()->fromRoute()['appId'];
        try {
            $response = $this->fieldService->deleteField($appId, $id);
        } catch (ServiceException $e) {
            return $this->getErrorResponse($e->getMessage(), 404);
        }
        if ($response == 0) {
            return $this->getErrorResponse("Field not found", 404, ['id' => $id]);
        }
        return $this->getSuccessResponse();
    }
    /**
     * GET Field API
     * @api
     * @link /field[/:fieldId]
     * @method GET
     * @param $id ID of Field
     * @return array $data
     * @return array Returns a JSON Response with Status Code and Created Field.
     */
    public function get($id)
    {
        $appId = $this->params()->fromRoute()['appId'];
        $result = $this->fieldService->getField($appId, $id);
        if ($result == 0) {
            return $this->getErrorResponse("Field not found", 404, ['id' => $id]);
        }
        return $this->getSuccessResponseWithData($result);
    }

    public function searchIndexAction()
    {
        $params = $this->params()->fromRoute();
        $result = $this->fieldService->getSearchIndexFields($params);
        return $this->getSuccessResponseWithData($result);
    }
}
