<?php
require __DIR__ .'/config/autoload/local.php';

return array(
        'dbname'   => $db,
        'user'     => $username,
        'password' => $password,
        'host'     => $host,
        'driver'   => 'pdo_mysql',
);
