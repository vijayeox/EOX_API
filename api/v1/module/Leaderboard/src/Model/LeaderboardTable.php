<?php

namespace Leaderboard\Model;

use Oxzion\Db\ModelTable;
use Zend\Db\TableGateway\TableGatewayInterface;
use Oxzion\Model\Entity;

class LeaderboardTable extends ModelTable {

    public function __construct(TableGatewayInterface $tableGateway) {
        parent::__construct($tableGateway);
    }

    public function save(Entity $data) {
        return $this->internalSave($data->toArray());
    }

}