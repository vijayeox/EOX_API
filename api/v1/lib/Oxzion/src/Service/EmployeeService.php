<?php
namespace Oxzion\Service;

use Exception;
use Oxzion\Auth\AuthConstants;
use Oxzion\Auth\AuthContext;
use Oxzion\EntityNotFoundException;
use Oxzion\Model\Account;
use Oxzion\Model\Employee;
use Oxzion\Model\EmployeeTable;
use Oxzion\OxServiceException;
use Oxzion\ServiceException;
use Oxzion\Service\AbstractService;
use Oxzion\Utils\UuidUtil;
use Oxzion\ValidationException;

class EmployeeService extends AbstractService
{
    protected $table;
    protected $modelClass;
    /**
     * @ignore __construct
     */
    public function __construct($config, $dbAdapter, EmployeeTable $table)
    {
        parent::__construct($config, $dbAdapter);
        $this->table = $table;
        $this->modelClass = new Employee();
    }

    public function addEmployeeRecord(&$data)
    {
        $this->logger->info("Adding Employee Record - " . print_r($data, true));
        if (isset($data['managerId']) && !empty($data['managerId'])) {
            $data['manager_id'] = $this->getIdFromUuid('ox_user', $data['managerId']);
        }
        $EmpData = $data;
        $EmpData['uuid'] = UuidUtil::uuid();
        $EmpData['created_by'] = AuthContext::get(AuthConstants::USER_ID) ? AuthContext::get(AuthConstants::USER_ID) : 1;
        $EmpData['date_created'] = date('Y-m-d H:i:s');
        $account = $this->getDataByParams('ox_account', array('organization_id', 'type'), array('id' => $EmpData['account_id']))->toArray();
        if ($account[0]['type'] != Account::BUSINESS) {
            return 0;
        }
        $EmpData['org_id'] = $account[0]['organization_id'];
        if (!isset($EmpData['date_of_join'])) {
            $EmpData['date_of_join'] = date('Y-m-d');
        }
        if (!isset($EmpData['designation'])) {
            $EmpData['designation'] = "Staff";
        }

        $form = new Employee($this->table);
        $form->assign($EmpData);
        try {
            $this->beginTransaction();
            $form->save();
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    public function updateEmployeeDetails($data)
    {
        $this->logger->info("Employee data--------\n" . print_r($data, true));
        if (isset($data['managerId'])) {
            $queryString = "SELECT e.id from ox_employee e
                            inner join ox_user u on u.person_id = e.person_id
                            where u.uuid = :userId";
            $params = ['userId' => $data['managerId']];
            $resultSet = $this->executeQueryWithBindParameters($queryString, $params)->toArray();
            if (count($resultSet) > 0) {
                $employeeId = $resultSet[0]['id'];
                $data['manager_id'] = $employeeId;
            }
        }
        $emp = $this->getDataByParams('ox_employee', array('id', 'uuid'), array('person_id' => $data['person_id']))->toArray();
        if (count($emp) == 0) {
            return;
        }
        $id = $emp[0]['id'];
        $form = new Employee($this->table);
        $form->loadById($id);
        unset($data['id']);
        unset($data['uuid']);
        $EmpData = $data;
        $filter = null;
        if (isset($data['accountId'])) {
            $filter = ['uuid' => $data['accountId']];
        } elseif (isset($data['account_id'])) {
            $filter = ['id' => $data['account_id']];
        }
        if ($filter) {
            $tempData = $this->getDataByParams('ox_account', ['organization_id'], $filter)->toArray();
            if (!empty($tempData)) {
                $EmpData['org_id'] = $tempData[0]['organization_id'];
            }
        }
        if (!isset($EmpData['org_id'])) {
            $ex = new ValidationException(['organization' => 'Organization not selected for employee']);
            throw $ex;
        }
        $EmpData['modified_by'] = AuthContext::get(AuthConstants::USER_ID);
        $EmpData['date_modified'] = date('Y-m-d H:i:s');
        $form->assign($EmpData);
        try {
            $this->beginTransaction();
            $form->save();
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    private function getEmployeeAndManagerIdForUsers($userId, $managerId)
    {
        $queryString = "SELECT e.id from ox_employee e
                            inner join ox_user u on u.person_id = e.person_id
                            where u.uuid = :userId";
        $params = ['userId' => $userId];
        $resultSet = $this->executeQueryWithBindParameters($queryString, $params)->toArray();
        if (empty($resultSet)) {
            throw new EntityNotFoundException("User not found");
        }
        $employeeId = $resultSet[0]['id'];
        $params = ['userId' => $managerId];
        $resultSet = $this->executeQueryWithBindParameters($queryString, $params)->toArray();
        if (empty($resultSet)) {
            throw new EntityNotFoundException("Manager not found");
        }
        $manId = $resultSet[0]['id'];
        return ['employee_id' => $employeeId,
            'manager_id' => $manId];
    }
    /**
     * @method assignManagerToUser
     * @param $id ID of User to assign a manager
     * @param $id ID of User to set as Manager
     * @return array success|failure response
     */
    //  TODO CHANGE TO UUID //
    public function assignManagerToEmployee($userId, $managerId)
    {
        $ids = $this->getEmployeeAndManagerIdForUsers($userId, $managerId);
        $query = "SELECT * from ox_employee_manager where employee_id = :employee_id
                    AND manager_id = :manager_id";
        $result = $this->executeQueryWithBindParameters($query, $ids)->toArray();
        if (!empty($result)) {
            throw new ServiceException("Employee already assigned to the manager", "already.assigned.to.manager", OxServiceException::ERR_CODE_NOT_ACCEPTABLE);
        }
        $sql = $this->getSqlObject();
        $insert = $sql->insert('ox_employee_manager');
        $data = array('employee_id' => $ids['employee_id'], 'manager_id' => $ids['manager_id'], 'created_id' => AuthContext::get(AuthConstants::USER_ID), 'date_created' => date('Y-m-d H:i:s'));
        $insert->values($data);
        $this->executeUpdate($insert);
    }

    /**
     * @method removeManagerForUser
     * @param $id ID of User to remove a manager
     * @param $id ID of User to remove as Manager
     * @return array success|failure response
     */
    public function removeManagerForEmployee($userId, $managerId)
    {
        $ids = $this->getEmployeeAndManagerIdForUsers($userId, $managerId);
        $sql = $this->getSqlObject();
        $delete = $sql->delete('ox_employee_manager');
        $delete->where(['employee_id' => $ids['employee_id'], 'manager_id' => $ids['manager_id']]);
        $result = $this->executeUpdate($delete);
    }
}
