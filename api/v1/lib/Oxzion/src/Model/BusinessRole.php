<?php
namespace Oxzion\Model;

use Oxzion\Model\Entity;
use Oxzion\Type;

class BusinessRole extends Entity
{
    protected static $MODEL = [
        'id' =>             ['type' => Type::INTEGER,   'readonly' => true,  'required' => false],
        'name' =>           ['type' => Type::STRING,    'readonly' => false, 'required' => true],
        'app_id' =>         ['type' => Type::INTEGER,   'readonly' => false, 'required' => true],
        'uuid' =>           ['type' => Type::UUID,      'readonly' => false, 'required' => false],
        'created_by' =>     ['type' => Type::INTEGER,   'readonly' => true,  'required' => false],
        'modified_by' =>    ['type' => Type::INTEGER,   'readonly' => true,  'required' => false],
        'date_created' =>   ['type' => Type::TIMESTAMP, 'readonly' => true,  'required' => false],
        'date_modified' =>  ['type' => Type::TIMESTAMP, 'readonly' => true,  'required' => false],
        'version' =>        ['type' => Type::INTEGER,   'readonly' => false, 'required' => false, 'value' => 1],
    ];

    public function &getModel()
    {
        return self::$MODEL;
    }
}
