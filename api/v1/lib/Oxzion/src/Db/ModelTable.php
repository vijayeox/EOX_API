<?php

namespace Oxzion\Db;

use Exception;
use Oxzion\Auth\AuthConstants;
use Oxzion\Auth\AuthContext;
use Oxzion\EntityNotFoundException;
use Oxzion\InsertFailedException;
use Oxzion\Model\Entity;
use Oxzion\MultipleRowException;
use Oxzion\ParameterRequiredException;
use Oxzion\ServiceException;
use Oxzion\UpdateFailedException;
use Oxzion\Utils\UuidUtil;
use Oxzion\VersionMismatchException;
use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Sql;
use Zend\Db\TableGateway\TableGateway;

abstract class ModelTable
{
    protected $tableGateway;
    protected $adapter;

    private $lastInsertValue;

    public function __construct(TableGateway $tableGateway)
    {
        $this->tableGateway = $tableGateway;
        $this->adapter = $tableGateway->getAdapter();
    }

    public function fetchAll(array $filter = null)
    {
        $this->init();
        return $this->tableGateway->select($filter);
    }

    protected function init()
    {
        $this->lastInsertValue = null;
    }

    public function get($id, array $filter = null)
    {
        $this->init();
        if (is_null($filter)) {
            $filter = array();
        }

        $filter[Entity::COLUMN_ID] = (int) $id;
        $rowset = $this->tableGateway->select($filter);
        $row = $rowset->current();
        return $row;
    }

    public function getByUuid($uuid, array $filter = null)
    {
        $this->init();
        if (is_null($filter)) {
            $filter = array();
        }

        $filter[Entity::COLUMN_UUID] = $uuid;
        $rowset = $this->tableGateway->select($filter);
        if (0 == count($rowset)) {
            return null;
        }
        if (count($rowset) > 1) {
            throw new MultipleRowException(
                'Multiple rows found when queried by UUID.',
                ['table' => $this->tableGateway->getTable(), 'uuid' => $uuid]
            );
        }
        $row = $rowset->current();
        return $row;
    }

    public function delete($id, array $filter = null)
    {
        $this->init();
        if (is_null($filter)) {
            $filter = array();
            $filter[Entity::COLUMN_ID] = $id; // You cannot have a filter and an id. If there is filter, then id is irrelavant.
        } else {
            $filter[Entity::COLUMN_ID] = $id;
        }
        return $this->tableGateway->delete($filter);
    }

    public function deleteByUuid($uuid, array $filter = null)
    {
        $this->init();
        if (is_null($filter)) {
            $filter = array();
            $filter[Entity::COLUMN_UUID] = $uuid; // You cannot have a filter and an id. If there is filter, then id is irrelavant.
        } else {
            $filter[Entity::COLUMN_UUID] = $uuid;
        }
        return $this->tableGateway->delete($filter);
    }

    public function update(array $data, array $filter)
    {
        return $this->tableGateway->update($data, $filter);
    }

    public function getLastInsertValue()
    {
        return $this->lastInsertValue;
    }

    public function queryExecute($select, $sql)
    {
        $selectString = $sql->getSqlStringForSqlObject($select);
        $results = $this->adapter->query($selectString, Adapter::QUERY_MODE_EXECUTE);
        return $results->toArray();
    }

    public function getSqlObject()
    {
        return (new Sql($this->adapter));
    }

    public function getTableGateway()
    {
        return $this->tableGateway;
    }

    protected function internalSave(array $data)
    {
        $this->init();
        $id = null;
        if (!empty($data[Entity::COLUMN_ID])) {
            $id = $data[Entity::COLUMN_ID];
        }
        try {
        
            if (is_null($id) || $id === 0 || empty($id)) {
                $rows = $this->tableGateway->insert($data);
                if (!isset($rows)) {
                    return 0;
                }
                $this->lastInsertValue = $this->tableGateway->getLastInsertValue();
                
                return $rows;
            }
            return $this->tableGateway->update($data, [Entity::COLUMN_ID => $id]);
        } catch (Exception $e) {
            throw new ServiceException($e->getMessage(), 'save.error');
        }
    }

    private function setCreatedByAndDate(&$data)
    {
        if (array_key_exists(Entity::COLUMN_CREATED_BY, $data) && empty($data[Entity::COLUMN_CREATED_BY])) {
            $data[Entity::COLUMN_CREATED_BY] = AuthContext::get(AuthConstants::USER_ID);
        }
        if (array_key_exists(Entity::COLUMN_CREATED_DATE, $data) && empty($data[Entity::COLUMN_CREATED_DATE])) {
            $data[Entity::COLUMN_CREATED_DATE] = date('Y-m-d H:i:s');
        }
    }

    private function setModifiedByAndDate(&$data)
    {
        if (array_key_exists(Entity::COLUMN_MODIFIED_BY, $data) && empty($data[Entity::COLUMN_MODIFIED_BY])) {
            $data[Entity::COLUMN_MODIFIED_BY] = AuthContext::get(AuthConstants::USER_ID);
        }
        if (array_key_exists(Entity::COLUMN_MODIFIED_DATE, $data) && empty($data[Entity::COLUMN_MODIFIED_DATE])) {
            $data[Entity::COLUMN_MODIFIED_DATE] = date('Y-m-d H:i:s');
        }
    }

    private function checkAndIncrementVersion(array &$data)
    {
        $version = $data[Entity::COLUMN_VERSION];
        if (!isset($version)) {
            throw new ParameterRequiredException('Version number is required.', [Entity::COLUMN_VERSION]);
        }
        try {
            $adapter = $this->tableGateway->getAdapter();
            $statement = $adapter->createStatement('SELECT ' . Entity::COLUMN_VERSION . ' FROM ' . $this->tableGateway->getTable() .
                ' WHERE ' . Entity::COLUMN_ID . '=?', [$data[Entity::COLUMN_ID]]);
            $result = $statement->execute();
            //count cannot be > 1 when selected on id column. Therefore we don't check for count > 1.
            if (0 == $result->count()) {
                throw new EntityNotFoundException(
                    'Entity not found.',
                    ['entity' => $this->tableGateway->getTable(), 'id' => $data[Entity::COLUMN_ID]]
                );
            }
            $row = $result->current();
            $dbVersion = $row[Entity::COLUMN_VERSION];
        } catch (Exception $e) {
            throw new UpdateFailedException(
                'Database update failed.',
                ['table' => $this->tableGateway->getTable(), 'data' => $data],
                UpdateFailedException::ERR_CODE_INTERNAL_SERVER_ERROR,
                UpdateFailedException::ERR_TYPE_ERROR,
                $e
            );
        }
        if ($dbVersion != $version) {
            throw new VersionMismatchException($dbVersion);
        }
        $data[Entity::COLUMN_VERSION] = $version + 1;
    }

    public function internalSave2(array &$data)
    {
        // print_r($data);exit;
        $this->init();
        $id = null;
        if (array_key_exists(Entity::COLUMN_ID, $data) && isset($data[Entity::COLUMN_ID])) {
            $id = $data[Entity::COLUMN_ID];
        }

        if (!isset($id) || (0 == $id)) {
            if (array_key_exists(Entity::COLUMN_UUID, $data)) {
                //Check and set UUID only if user generated UUID is not set.
                $uuid = $data[Entity::COLUMN_UUID];
                if (!isset($uuid)) {
                    $data[Entity::COLUMN_UUID] = UuidUtil::uuid();
                }
            }
            if (array_key_exists(Entity::COLUMN_VERSION, $data)) {
                $data[Entity::COLUMN_VERSION] = 1; //Starting version number when the row is inserted in the database.
            }
            $this->setCreatedByAndDate($data);
            try {
                $rows = $this->tableGateway->insert($data);
            } catch (Exception $e) {
                if (strpos($e->getMessage(), 'Duplicate')) {
                    throw new InsertFailedException(
                        'Duplicate Record.',
                        ['table' => $this->tableGateway->getTable(), 'data' => $data],
                        InsertFailedException::ERR_CODE_INTERNAL_SERVER_ERROR,
                        InsertFailedException::ERR_TYPE_ERROR,
                        $e
                    );
                }
                throw new InsertFailedException(
                    $e->getMessage(),
                    ['table' => $this->tableGateway->getTable(), 'data' => $data],
                    InsertFailedException::ERR_CODE_INTERNAL_SERVER_ERROR,
                    InsertFailedException::ERR_TYPE_ERROR,
                    $e
                );
            }
            if (!isset($rows) || (1 != $rows)) {
                throw new InsertFailedException(
                    'Database insert failed.',
                    ['table' => $this->tableGateway->getTable(), 'data' => $data]
                );
            }
            $this->lastInsertValue = $this->tableGateway->getLastInsertValue();
            $data[Entity::COLUMN_ID] = $this->lastInsertValue;
            return $data;
        } else {
            $whereCondition = [Entity::COLUMN_ID => $id];
            if (array_key_exists(Entity::COLUMN_VERSION, $data)) {
                //IMPORTANT: version property in $whereCondition should be set before calling checkAndIncrementVersion
                $whereCondition[Entity::COLUMN_VERSION] = $data[Entity::COLUMN_VERSION];
                $this->checkAndIncrementVersion($data);
            }
            $this->setModifiedByAndDate($data);
            try {
                $rows = $this->tableGateway->update($data, $whereCondition);
            } catch (Exception $e) {
                throw new UpdateFailedException(
                    'Database update failed.',
                    ['table' => $this->tableGateway->getTable(), 'data' => $data],
                    UpdateFailedException::ERR_CODE_INTERNAL_SERVER_ERROR,
                    UpdateFailedException::ERR_TYPE_ERROR,
                    $e
                );
            }

            return $data;
        }
    }
}
