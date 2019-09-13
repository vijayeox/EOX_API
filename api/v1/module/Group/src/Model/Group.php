<?php
namespace Group\Model;

use Oxzion\Model\Entity;
use Oxzion\ValidationException;

class Group extends Entity
{
    protected $data = array(
        'id' => null,
        'uuid' => null,
        'name'=> '',
        'parent_id' => null,
        'org_id' => null,
        'manager_id' => null,
        'description' => '',
        'status' => "Active",
        'date_created' => null,
        'date_modified' => null,
        'created_id' => null,
        'modified_id' => null
    );

    public function validate()
    {
        $dataArray = array("name", "manager_id", "status", "date_created", "created_id");
        $this->validateWithParams($dataArray);
    }
}
