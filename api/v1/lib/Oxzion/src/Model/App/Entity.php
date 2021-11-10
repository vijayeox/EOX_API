<?php

namespace Oxzion\Model\App;

use Oxzion\Type;
use Oxzion\Model\Entity as OxzionEntity;
use Oxzion\ValidationException;

class Entity extends OxzionEntity
{
    protected static $MODEL = [
        'id' =>                 ['type' => Type::INTEGER,   'readonly' => TRUE , 'required' => FALSE],
        'uuid' =>               ['type' => Type::UUID,      'readonly' => FALSE, 'required' => FALSE],
        'start_date_field' =>   ['type' => Type::STRING,    'readonly' => FALSE, 'required' => FALSE],
        'end_date_field' =>     ['type' => Type::STRING,    'readonly' => FALSE, 'required' => FALSE],
        'status_field' =>       ['type' => Type::STRING,    'readonly' => FALSE, 'required' => FALSE],
        'name' =>               ['type' => Type::STRING,    'readonly' => FALSE, 'required' => TRUE],
        'app_id' =>             ['type' => Type::INTEGER,   'readonly' => FALSE, 'required' => TRUE],
        'assoc_id' =>           ['type' => Type::INTEGER,   'readonly' => FALSE, 'required' => FALSE],
        'description'=>         ['type' => Type::STRING,    'readonly' => FALSE, 'required' => FALSE],
        'override_data' =>      ['type' => Type::BOOLEAN,   'readonly' => FALSE, 'required' => FALSE, 'value' => FALSE],
        'date_created' =>       ['type' => Type::TIMESTAMP, 'readonly' => TRUE,  'required' => FALSE],
        'date_modified' =>      ['type' => Type::TIMESTAMP, 'readonly' => TRUE,  'required' => FALSE],
        'created_by' =>         ['type' => Type::INTEGER,   'readonly' => TRUE,  'required' => FALSE],
        'modified_by' =>        ['type' => Type::INTEGER,   'readonly' => TRUE,  'required' => FALSE],
        'ryg_rule' =>           ['type' => Type::STRING,    'readonly' => FALSE, 'required' => FALSE],
        'enable_comments' =>    ['type' => Type::BOOLEAN,   'readonly' => FALSE, 'required' => FALSE],
        'enable_documents' =>   ['type' => Type::BOOLEAN,   'readonly' => FALSE, 'required' => FALSE],
        'enable_view' =>        ['type' => Type::BOOLEAN,   'readonly' => FALSE, 'required' => FALSE],
        'enable_auditlog' =>    ['type' => Type::BOOLEAN,   'readonly' => FALSE, 'required' => FALSE],
        'title' =>              ['type' => Type::STRING,    'readonly' => FALSE, 'required' => FALSE],
        'page_id' =>            ['type' => Type::INTEGER,   'readonly' => FALSE, 'required' => FALSE],
        'subscriber_field' =>   ['type' => Type::STRING,    'readonly' => FALSE, 'required' => FALSE],
        'generic_attachment_config' =>   ['type' => Type::STRING,    'readonly' => FALSE, 'required' => FALSE]
    ];
    
    public function &getModel()
    {
        return self::$MODEL;
    }
}
