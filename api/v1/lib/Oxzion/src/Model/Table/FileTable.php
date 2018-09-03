<?php
namespace Oxzion\Model\Table;

use Oxzion\Db\ModelTable;
use Oxzion\Model\Model;
use Zend\Db\TableGateway\TableGatewayInterface;
use Oxzion\Model\Entity\File;

class FileTable extends ModelTable {
    public function __construct() {
    	$this->tablename = 'instanceforms';
        parent::__construct(new File());
    }
    public function save(Model $data){
        return $this->internalSave($data->toArray());
    }
}