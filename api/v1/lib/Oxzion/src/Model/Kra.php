<?php

namespace Oxzion\Model;

use Oxzion\Type;
use Oxzion\Model\Entity;

class Kra extends Entity
{
    protected static $MODEL = [
        'id' =>                     ['type' => Type::INTEGER,   'readonly' => true , 'required' => false],
        'uuid' =>                   ['type' => Type::UUID,      'readonly' => false, 'required' => false],
        'query_id' =>               ['type' => Type::INTEGER,   'readonly' => false, 'required' => true],
        'target_id' =>              ['type' => Type::INTEGER,   'readonly' => false, 'required' => false],
        'account_id' =>             ['type' => Type::INTEGER,   'readonly' => false, 'required' => false],
        'type' =>                   ['type' => Type::STRING,    'readonly' => false, 'required' => false],
        'name' =>                   ['type' => Type::STRING,    'readonly' => false, 'required' => false],
        'business_role_id' =>       ['type' => Type::INTEGER,   'readonly' => false, 'required' => false],
        'status' =>                 ['type' => Type::STRING,    'readonly' => false, 'required' => false],
        'user_id' =>                ['type' => Type::INTEGER,   'readonly' => false, 'required' => false],
        'team_id' =>                ['type' => Type::INTEGER,   'readonly' => false, 'required' => false],
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
