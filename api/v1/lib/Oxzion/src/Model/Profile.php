<?php

namespace Oxzion\Model;

use Oxzion\Type;
use Oxzion\Model\Entity;

class Profile extends Entity
{
    protected static $MODEL = [
        'id' =>                     ['type' => Type::INTEGER,   'readonly' => true , 'required' => false],
        'uuid' =>                   ['type' => Type::UUID,      'readonly' => false, 'required' => false],
        'name' =>                   ['type' => Type::STRING,    'readonly' => false, 'required' => false],
        'dashboard_uuid' =>         ['type' => Type::STRING,   'readonly' => false, 'required' => false],
        'html' =>                   ['type' => Type::STRING,    'readonly' => false, 'required' => false],
        'type' =>                   ['type' => Type::STRING,    'readonly' => false, 'required' => false],
        'role_id' =>                ['type' => Type::INTEGER,   'readonly' => false, 'required' => false],
        'precedence' =>             ['type' => Type::INTEGER,   'readonly' => false, 'required' => false],
        'date_created' =>           ['type' => Type::TIMESTAMP, 'readonly' => true,  'required' => false],
        'date_modified' =>          ['type' => Type::TIMESTAMP, 'readonly' => true,  'required' => false],
        'created_by' =>             ['type' => Type::INTEGER,   'readonly' => false, 'required' => false],
        'modified_by' =>            ['type' => Type::INTEGER,   'readonly' => true,  'required' => false]
    ];

    public function &getModel()
    {
        return self::$MODEL;
    }
}
