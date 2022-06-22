<?php
namespace UserAuditLog\Model;

use Oxzion\Db\ModelTable;
use Oxzion\Model\Entity;
use Zend\Db\TableGateway\TableGatewayInterface;

class UserAuditLogTable extends ModelTable
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
