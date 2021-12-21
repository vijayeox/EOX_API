<?php
namespace Oxzion\Model;

use Oxzion\ValidationException;
use Oxzion\Model\Entity;

class Field extends Entity
{
    protected $data = array(
        'id'=>0,
        'app_id'=>null,
        'name'=>null,
        'text'=>null,
        'entity_id'=>null,
        'data_type'=>null,
        'type' => null,
        'parent_id' => null,
        'options'=>null,
        'template'=>null,
        'constraints'=>null,
        'properties'=>null,
        'dependson'=>null,
        'required'=>null,
        'readonly'=>null,
        'expression'=>null,
        'validationtext'=>null,
        'helpertext'=>null,
        'sequence'=>null,
        'created_by'=>null,
        'modified_by'=>null,
        'date_created'=>null,
        'date_modified'=>null,
        'index' => 0,
        'sequence' => null,
        'isdeleted' => 0,
        'uuid' =>0,
    );

    public function validate()
    {
        $required = array('app_id','name','data_type');
        $this->validateWithParams($required);
    }
}
