<?php

namespace Oxzion\Model;

use Oxzion\Db\ModelTable;
use Zend\Db\TableGateway\TableGatewayInterface;

class AppTable extends ModelTable
{
    public function __construct(TableGatewayInterface $tableGateway)
    {
        parent::__construct($tableGateway);
    }

    public function getByName($name)
    {
        $this->init();
        $filter = array();
        $filter['name'] = $name;
        $rowset = $this->tableGateway->select($filter);
        $row = $rowset->current();
        return $row;
    }
}
