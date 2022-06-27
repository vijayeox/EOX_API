<?php

namespace Oxzion\Utils;

class ServerUtils
{

    public static function isProduction()
    {
        return (strtolower(getenv('ENV')) == 'production');
    }

}