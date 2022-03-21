<?php

/**
 * Local Configuration Override
 *
 * This configuration override file is for overriding environment-specific and
 * security-sensitive configuration information. Copy this file without the
 * .dist extension at the end and populate values as needed.
 *
 * @NOTE: This file is ignored from Git by default with the .gitignore included
 * in ZendSkeletonApplication. This is a good practice, as it prevents sensitive
 * credentials from accidentally being committed into version control.
 */
use Doctrine\DBAL\Driver\PDOMySql\Driver as PDOMySqlDriver;

$host = 'localhost';
$db = 'oxzionapi';
$username = 'root';
$password = 'root';

if(isset($_ENV['ENV']) && $_ENV['ENV'] == 'test'){
    $host = 'localhost';
    $db = "oxzionapi_test";
    $username = "root";
    $password = "root";
}

return [
	'db' => [
    	'driver' => 'Pdo_Mysql',
        'database' => $db,
        'host' => $host,
        'username' => $username,
        'password' => $password,
    	'dsn' => 'mysql:dbname=' . $db . ';host=' . $host . ';charset=utf8;username=' . $username . ';password=' . $password,
    ],
    'chat' => [
        'chatServerUrl' => 'http://localhost:8065/',
        'authToken' => '',
        'appBotUrl' => 'plugins/com.code.oxzion.mattermost.appbot-plugin/'
    ],
    'crm' => [
        'crmServerUrl' => 'http://localhost:8075/crm/public/',
        'authToken' => ''
    ],
    'task' => [
        'taskServerUrl' => 'http://localhost:8050/api/v3/',
        'username' => 'apikey',
        'authToken' => '8a808c02d92dcef6153b996d2a0387230982c797111b6a9c7e5d3cdfff5713ff'
    ],
    'calendar' => [
        'calendarServerUrl' => 'http://localhost:8075/calendar',
        'authToken' => ''
    ],
    'internalBaseUrl' => 'http://localhost:8080/',
    'DELEGATE_FOLDER'=>'/app/api/data/delegate/',
    'ENTITY_FOLDER'=>'/app/api/data/entity/',
    'FORM_FOLDER'=>'/app/api/data/forms/',
    'PAGE_FOLDER'=>'/app/api/data/pages/',
    'applicationUrl' => 'http://localhost:8081',
    'RULE_FOLDER'=>'/app/api/data/rules/',
    'DATA_FOLDER'=>'/app/api/data/',
    "UPLOAD_FOLDER" => '/app/api/data/uploads/',
    'TEMPLATE_FOLDER'=>'/app/api/data/template/',
    'APP_UPLOAD_FOLDER' => '/app/api/data/app',
    'APP_DOCUMENT_FOLDER' => '/app/api/data/file_docs/',
    'CLIENT_FOLDER' => '/app/clients/',
    'APPS_FOLDER' => "/app/view/apps/",
    'GUI_FOLDER' => "/app/view/gui/src/externals/",
    'THEME_FOLDER' => "/app/view/themes/",
    'EOX_APP_SOURCE_DIR' => '/app/api/data/AppSource/',
    'EOX_APP_DEPLOY_DIR' => '/app/api/data/AppDeploy/',
    'TRANSFORMER_FOLDER'=>'/app/api/data/transformer/',
];
