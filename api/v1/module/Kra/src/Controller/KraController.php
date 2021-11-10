<?php

namespace Kra\Controller;

use Exception;
use Oxzion\Model\Kra;
use Oxzion\Model\KraTable;
use Oxzion\Service\KraService;
use Oxzion\AccessDeniedException;
use Oxzion\Controller\AbstractApiController;
use Oxzion\ServiceException;
use Oxzion\Service\AccountService;
use Oxzion\ValidationException;
use Zend\Db\Adapter\AdapterInterface;

class KraController extends AbstractApiController
{
    private $kraService;
    private $accountService;

    /**
     * @ignore __construct
     */
    public function __construct(KraTable $table, KraService $kraService, AdapterInterface $dbAdapter, AccountService $accountService)
    {
        parent::__construct($table, Kra::class);
        $this->setIdentifierName('kraId');
        $this->kraService = $kraService;
        $this->accountService = $accountService;
        $this->log = $this->getLogger();
    }

    /**
     * ! DEPRECATED
     * GET Kra API The code is to get the list of all the kras for the user. I am putting this function here, but Im not sure whether this has to be here or in the User Module. We can move that later when it is required.
     * @api
     * @link /kra/getKrasforUser/:userId
     * @method GET
     * @param $id ID of Kra
     * @return array $data
     * @return array Returns a JSON Response with Status Code and Created Kra.
     */
    public function getKrasforUserAction()
    {
        $params = $this->params()->fromRoute();
        $data = $this->params()->fromQuery();
        $userId = $params['userId'];
        try {
            $kraList = $this->kraService->getKrasforUser($userId, $data);
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
        return $this->getSuccessResponseWithData($kraList);
    }

    public function getKrasforBusinessRoleAction() {
        $params = $this->params()->fromRoute();
        $data = $this->params()->fromQuery();
        $businessRole = $params['businessRole'];
        try {
            $kraList = $this->kraService->getKrasforBusinessRole($businessRole, $data);
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
        return $this->getSuccessResponseWithData($kraList);
    }
    /**
     * Create Kra API
     * @api
     * @link /kra
     * @method POST
     * @param array $data Array of elements as shown
     * <code> {
     *               id : integer,
     *               name : string,
     *               Fields from Kra
     *   } </code>
     * @return array Returns a JSON Response with Status Code and Created Kra.
     */
    public function create($data)
    {
        $id = $this->params()->fromRoute();
        $id['accountId'] = isset($id['accountId']) ? $id['accountId'] : null;
        $this->log->info(__CLASS__ . "-> Create Kra - " . json_encode($data, true));
        try {
            if (!isset($id['kraId'])) {
                $this->kraService->createKra($data, $id['accountId']);
            } else {
                $this->kraService->updateKra($id['kraId'], $data, $id['accountId']);
            }
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
        return $this->getSuccessResponseWithData($data, 201);
    }

    /**
     * Update Kra API
     * @api
     * @link /kra[/:kraId]
     * @method PUT
     * @param array $id ID of Kra to update
     * @param array $data
     * @return array Returns a JSON Response with Status Code and Created Kra.
     */
    public function update($id, $data)
    {
        $this->log->info(__CLASS__ . "-> Update Kra - " . json_encode($data, true));
        try {
            $this->kraService->updateKra($id, $data);
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
        return $this->getSuccessResponseWithData($data, 200);
    }

    /**
     * Delete Kra API
     * @api
     * @link /kra[/:kraId]
     * @method DELETE
     * @param $id ID of Kra to Delete
     * @return array success|failure response
     */
    public function delete($id)
    {
        $params = $this->params()->fromRoute();
        $this->log->info(__CLASS__ . "-> Delete Kra - " . json_encode($params, true) . " for ID " .json_encode($id, true));
        try {
            $response = $this->kraService->deleteKra($params);
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
        return $this->getSuccessResponse();
    }

    /**
     * GET Kra API
     * @api
     * @link /kra[/:kraId]
     * @method GET
     * @param array $dataget of Kra
     * @return array $data
     * <code> {
     *               id : integer,
     *               name : string,
     *               logo : string,
     *               status : String(Active|Inactive),
     *   } </code>
     * @return array Returns a JSON Response with Status Code and Created Kra.
     */
    public function get($id)
    {
        $params = $this->params()->fromRoute();
        try {
            $result = $this->kraService->getKraByUuid($id, $params);
            if (count($result) == 0) {
                return $this->getSuccessResponseWithData($result);
            }
            $account = $this->accountService->getAccount($result['accountId']);
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
        return $this->getSuccessResponseWithData($result);
    }

    /**
     * GET List Kra API
     * @api
     * @link /kra
     * @method GET
     * @return array Returns a JSON Response with Invalid Method/
     */
    public function getList()
    {
        $filterParams = $this->params()->fromQuery();
        $params = $this->params()->fromRoute();
        $this->log->info(__CLASS__ . "-> Get List - " . json_encode($params, true));
        try {
            $result = $this->kraService->getKraList($filterParams, $params);
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
        return $this->getSuccessResponseDataWithPagination($result['data'], $result['total']);
    }

    public function krasListAction()
    {
        $filterParams = $this->extractPostData();
        $params = $this->params()->fromRoute();
        $this->log->info(__CLASS__ . "-> Kra Listing - " . json_encode($params, true));
        try {
            $result = $this->kraService->getKraList($filterParams, $params);
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
        return $this->getSuccessResponseDataWithPagination($result['data'], $result['total']);
    }
}
