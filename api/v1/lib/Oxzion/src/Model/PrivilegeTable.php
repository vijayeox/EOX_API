<?php
namespace Oxzion\Model;

use Bos\Db\ModelTable;
use Zend\Db\TableGateway\TableGatewayInterface;
use Bos\Model\Entity;

class PrivilegeTable extends ModelTable
{
    protected $tableGateway;

    public function __construct(TableGatewayInterface $tableGateway)
    {
        parent::__construct($tableGateway);
        $this->tableGateway = $tableGateway;
    }

    public function save(Entity $data)
    {
        $data = $data->toArray();
        return $this->internalSave($data);
    }
}