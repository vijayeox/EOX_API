<?php

namespace App;

use Oxzion\Utils\UuidUtil;
use Zend\Router\Http\Segment;

return [
    'router' => [
        'routes' => [
            'app' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/app[/:appId]',
                    'constraints' => [
                        'appId' => UuidUtil::UUID_PATTERN,
                    ],
                    'defaults' => [
                        'controller' => Controller\AppController::class,
                        'access' => [
                            // SET ACCESS CONTROL
                            'put' => 'MANAGE_APPLICATION_WRITE',
                            'post' => 'MANAGE_APPLICATION_WRITE',
                            'delete' => 'MANAGE_APPLICATION_WRITE',
                            'get' => 'MANAGE_APPLICATION_READ',
                        ],
                    ],
                ],
            ],
            'deployapp' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/app/deployapp',
                    'defaults' => [
                        'controller' => Controller\AppController::class,
                        'action' => 'deployApp',
                        'method' => 'post',
                    ],
                ],
            ],
            'deployApplication' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/app/:appId/deploy',
                    'constraints' => [
                        'appId' => UuidUtil::UUID_PATTERN,
                    ],
                    'defaults' => [
                        'controller' => Controller\AppController::class,
                        'method' => 'POST',
                        'action' => 'deployApplication',
                        'access' => [
                            // SET ACCESS CONTROL
                        ],
                    ],
                ],
            ],
            'removeapp' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/app/:appId/removeapp',
                    'constraints' => [
                        'appId' => UuidUtil::UUID_PATTERN,
                    ],
                    'defaults' => [
                        'controller' => Controller\AppController::class,
                        'action' => 'removeapp',
                        'method' => 'DELETE',
                    ],
                ],
            ],
            'applist' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/app/a',
                    'defaults' => [
                        'controller' => Controller\AppController::class,
                        'action' => 'applist',
                        'method' => 'GET',
                    ],
                ],
            ],
            'appupload' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/app/:appId/entity/:entityId/deployworkflow[/:workflowId]',
                    'constraints' => [
                        'workflowId' => UuidUtil::UUID_PATTERN,
                    ],
                    'defaults' => [
                        'controller' => Controller\EntityController::class,
                        'action' => 'workflowDeploy',
                        'method' => 'post',
                    ],
                ],
            ],
            'appregister' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/app/register',
                    'defaults' => [
                        'controller' => Controller\AppRegisterController::class,
                        'action' => 'appregister',
                        'method' => 'POST',
                    ],
                ],
            ],
            'appSetupToOrg' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/app/:appId/:serviceType/account/:accountId',
                    'constraints' => [
                        'accountId' => UuidUtil::UUID_PATTERN,
                        'appId' => UuidUtil::UUID_PATTERN,
                        'serviceType' => 'install|uninstall',
                    ],
                    'defaults' => [
                        'controller' => Controller\AppController::class,
                        'action' => 'appSetupToOrg',
                        'method' => 'POST',
                    ],
                ],
            ],
            'appQuery' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/app/:appId/query/:queryId',
                    'defaults' => [
                        'controller' => Controller\AppController::class,
                        'action' => 'appQuery',
                        'method' => 'GET',
                    ],
                ],
            ],
            'appform' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/app/:appId/form[/:id]',
                    'constraints' => [
                        'appId' => UuidUtil::UUID_PATTERN,
                        'id' => UuidUtil::UUID_PATTERN,
                    ],
                    'defaults' => [
                        'controller' => Controller\FormController::class,
                        'access' => [
                            // SET ACCESS CONTROL
                            'put' => 'MANAGE_FORM_WRITE',
                            'post' => 'MANAGE_FORM_WRITE',
                            'delete' => 'MANAGE_FORM_WRITE',
                            // 'get'=> 'MANAGE_FORM_READ',
                            // fix to get form template available for csr
                        ],
                    ],
                ],
            ],
            'appfile' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/app/:appId/file/crud[/:id]',
                    'constraints' => [
                        'appId' => UuidUtil::UUID_PATTERN,
                        'id' => UuidUtil::UUID_PATTERN,
                    ],
                    'defaults' => [
                        'controller' => Controller\FileController::class,
                        'access' => [
                            // SET ACCESS CONTROL
                            'put' => 'MANAGE_FILE_WRITE',
                            'post' => 'MANAGE_FILE_WRITE',
                            'delete' => 'MANAGE_FILE_WRITE',
                            'get' => 'MANAGE_FILE_READ',
                        ],
                    ],
                ],
            ],
            'appfield' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/app/:appId/field[/:id]',
                    'constraints' => [
                        'appId' => UuidUtil::UUID_PATTERN,
                        'id' => UuidUtil::UUID_PATTERN,
                    ],
                    'defaults' => [
                        'controller' => Controller\FieldController::class,
                        'access' => [
                            // SET ACCESS CONTROL
                            'put' => 'MANAGE_FIELD_WRITE',
                            'post' => 'MANAGE_FIELD_WRITE',
                            'delete' => 'MANAGE_FIELD_WRITE',
                            'get' => 'MANAGE_FIELD_READ',
                        ],
                    ],
                ],
            ],
            'searchIndexfield' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/app/:appId/searchIndex[/:entityName]',
                    'constraints' => [
                        'appId' => UuidUtil::UUID_PATTERN
                    ],
                    'defaults' => [
                        'controller' => Controller\FieldController::class,
                        'action' => "searchIndex",
                        'method' => 'GET'
                    ],
                ],
            ],
            'appDelegate' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/app/:appId/delegate/:delegate',
                    'constraints' => [
                        'appId' => UuidUtil::UUID_PATTERN,
                        'delegate' => '[A-Za-z0-9]*',
                    ],
                    'defaults' => [
                        'controller' => Controller\AppDelegateController::class,
                        'action' => 'delegate',
                        'method' => 'POST',
                    ],
                ],
            ],
            'delegateCommand' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/app/:appId/command/delegate/:delegate',
                    'constraints' => [
                        'appId' => UuidUtil::UUID_PATTERN,
                        'delegate' => '[A-Za-z0-9]*',
                    ],
                    'defaults' => [
                        'controller' => Controller\AppController::class,
                        'action' => 'delegateCommand',
                        'method' => 'POST',
                    ],
                ],
            ],
            'appworkflow' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/app/:appId/workflow[/:workflowId]',
                    'constraints' => [
                        'workflowId' => UuidUtil::UUID_PATTERN,
                        'appId' => UuidUtil::UUID_PATTERN,
                    ],
                    'defaults' => [
                        'controller' => Controller\WorkflowController::class,
                        'access' => [
                            // SET ACCESS CONTROL
                            // 'put'=> 'MANAGE_FORM_WRITE',
                            // 'post'=> 'MANAGE_FORM_WRITE',
                            // 'delete'=> 'MANAGE_FORM_WRITE',
                            // 'get'=> 'MANAGE_FORM_READ',
                        ],
                    ],
                ],
            ],
            'appmenu' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/app/:appId/menu[/:menuId]',
                    'constraints' => [
                        'appId' => UuidUtil::UUID_PATTERN,
                        'menuId' => UuidUtil::UUID_PATTERN,
                    ],
                    'defaults' => [
                        'controller' => Controller\MenuItemController::class,
                        'access' => [
                            // SET ACCESS CONTROL
                            // 'put'=> 'MANAGE_MENU_WRITE',
                            // 'post'=> 'MANAGE_MENU_WRITE',
                            // 'delete'=> 'MANAGE_MENU_WRITE',
                            // 'get'=> 'MANAGE_MENU_READ',
                        ],
                    ],
                ],
            ],
            'apppage' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/app/:appId[/account/:accountId]/page[/:pageId]',
                    'constraints' => [
                        'appId' => UuidUtil::UUID_PATTERN,
                        'accountId' => UuidUtil::UUID_PATTERN,
                        'pageId' => UuidUtil::UUID_PATTERN,
                    ],
                    'defaults' => [
                        'controller' => Controller\PageController::class,
                        'access' => [
                            // SET ACCESS CONTROL
                            // 'put'=> 'MANAGE_PAGE_WRITE',
                            // 'post'=> 'MANAGE_PAGE_WRITE',
                            // 'delete'=> 'MANAGE_PAGE_WRITE',
                            // 'get'=> 'MANAGE_PAGE_READ',
                        ],
                    ],
                ],
            ],
            'apppagecontent' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/app/:appId/pagecontent[/:pageContentId]',
                    'constraints' => [
                        'appId' => UuidUtil::UUID_PATTERN,
                    ],
                    'defaults' => [
                        'controller' => Controller\PageContentController::class,
                        'access' => [
                            // SET ACCESS CONTROL
                            // 'put'=> 'MANAGE_PAGE_WRITE',
                            // 'post'=> 'MANAGE_PAGE_WRITE',
                            // 'delete'=> 'MANAGE_PAGE_WRITE',
                            // 'get'=> 'MANAGE_PAGE_READ',
                        ],
                    ],
                ],
            ],
            'assignments' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/app/:appId/assignments',
                    'constraints' => [
                        'appId' => UuidUtil::UUID_PATTERN,
                    ],
                    'defaults' => [
                        'controller' => Controller\AppController::class,
                        'action' => 'assignments',
                        'access' => [
                        ],
                    ],
                ],
            ],
            'assignmentList' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/assignmentList',
                    'constraints' => [
                        'appId' => UuidUtil::UUID_PATTERN,
                    ],
                    'defaults' => [
                        'controller' => Controller\AppController::class,
                        'action' => 'assignments',
                        'access' => [
                        ],
                    ],
                ],
            ],
            'followups' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/followups[/createdBy/:createdBy]',
                    'constraints' => [
                        'appId' => UuidUtil::UUID_PATTERN,
                    ],
                    'defaults' => [
                        'controller' => Controller\FileController::class,
                        'method' => 'GET',
                        'action' => 'getFileList',
                        'access' => [
                        ],
                    ],
                ],
            ],
            'form_workflow' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/app/:appId/form/:formId/workflow',
                    'constraints' => [
                        'appId' => UuidUtil::UUID_PATTERN,
                        'formId' => UuidUtil::UUID_PATTERN,
                    ],
                    'defaults' => [
                        'controller' => Controller\FormController::class,
                        'action' => 'getWorkflow',
                        'method' => 'GET',
                    ],
                ],
            ],
            'importcsv' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/app/:appId/importcsv',
                    'constraints' => [
                        'appId' => UuidUtil::UUID_PATTERN,
                    ],
                    'defaults' => [
                        'controller' => Controller\ImportController::class,
                        'action' => 'importCSV',
                        'method' => 'POST',
                    ],
                ],
            ],
            'startform' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/app/:appId/[entity/:entityId/]workflow/:workflowId/startform',
                    'constraints' => [
                        'appId' => UuidUtil::UUID_PATTERN,
                        'workflowId' => UuidUtil::UUID_PATTERN,
                    ],
                    'defaults' => [
                        'controller' => Controller\WorkflowController::class,
                        'action' => 'startform',
                        'method' => 'POST',
                    ],
                ],
            ],
            'storecache' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/app/:appId/storecache[/:cacheId]',
                    'constraints' => [
                        'appId' => UuidUtil::UUID_PATTERN,
                    ],
                    'defaults' => [
                        'controller' => Controller\CacheController::class,
                        'action' => 'store',
                    ],
                ],
            ],
            'app_cache' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/app/:appId/cache[/:cacheId]',
                    'constraints' => [
                        'appId' => UuidUtil::UUID_PATTERN,
                    ],
                    'defaults' => [
                        'controller' => Controller\CacheController::class,
                        'action' => 'cache',
                    ],
                ],
            ],
            'form_cache' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/app/:appId/formCache[/workflowId/:workflowId][/formId/:formId]',
                    'constraints' => [
                        'appId' => UuidUtil::UUID_PATTERN,
                    ],
                    'defaults' => [
                        'controller' => Controller\CacheController::class,
                        'action' => 'formcache',
                    ],
                ],
            ],
            'remove_app_cache' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/app/:appId/deletecache[/:cacheId]',
                    'constraints' => [
                        'appId' => UuidUtil::UUID_PATTERN,
                    ],
                    'defaults' => [
                        'controller' => Controller\CacheController::class,
                        'action' => 'cacheDelete',
                        'method' => 'DELETE',
                    ],
                ],
            ],
            'getdocument' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/app/:appId/file/:fileId/document/:documentName',
                    'constraints' => [
                        'appId' => UuidUtil::UUID_PATTERN,
                        'fileId' => UuidUtil::UUID_PATTERN,
                    ],
                    'defaults' => [
                        'controller' => Controller\FileController::class,
                        'action' => 'getDocument',
                        'method' => 'GET',
                    ],
                ],
            ],
            'getauditlog' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/app/:appId/file/:fileId/audit',
                    'constraints' => [
                        'appId' => UuidUtil::UUID_PATTERN,
                        'fileId' => UuidUtil::UUID_PATTERN,
                    ],
                    'defaults' => [
                        'controller' => Controller\FileController::class,
                        'action' => 'audit',
                        'method' => 'GET',
                    ],
                ],
            ],
            'gettempdocument' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/:appId/data/:accountId/temp/:tempId/:documentName',
                    'constraints' => [
                        'appId' => UuidUtil::UUID_PATTERN,
                        'fileId' => UuidUtil::UUID_PATTERN,
                    ],
                    'defaults' => [
                        'controller' => Controller\DocumentController::class,
                        'action' => 'getTempDocument',
                        'method' => 'GET',
                    ],
                ],
            ],
            'fileData' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/app/:appId/workflowInstance/:workflowInstanceId',
                    'constraints' => [
                        'appId' => UuidUtil::UUID_PATTERN,
                        'workflowId' => UuidUtil::UUID_PATTERN,
                    ],
                    'defaults' => [
                        'controller' => Controller\FileController::class,
                        'action' => 'getFileData',
                        'method' => 'GET',
                    ],
                ],
            ],
            'appentity' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/app/:appId/entity[/:entityId]',
                    'defaults' => [
                        'controller' => Controller\EntityController::class,
                        'access' => [
                            // SET ACCESS CONTROL
                            // 'put'=> 'MANAGE_PAGE_WRITE',
                            // 'post'=> 'MANAGE_PAGE_WRITE',
                            // 'delete'=> 'MANAGE_PAGE_WRITE',
                            // 'get'=> 'MANAGE_PAGE_READ',
                        ],
                    ],
                ],
            ],
            'entity_page' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/app/:appId/entity/:entityId/page',
                    'defaults' => [
                        'controller' => Controller\EntityController::class,
                        'action' => 'page',
                        'method' => 'GET',
                    ],
                ],
            ],
            'fileremainder' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/app/:appId/remainder',
                    'defaults' => [
                        'controller' => Controller\FileController::class,
                        'action' => 'sendReminder',
                        'method' => 'POST',
                        'access' => [
                        ],
                    ],
                ],
            ],
            'filelisting' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/app/:appId[/entity/:entityName][/workflow/:workflowId][/:userId]/file',
                    'constraints' => [
                        'appId' => UuidUtil::UUID_PATTERN,
                        'workflowId' => UuidUtil::UUID_PATTERN,
                        'userId' => UuidUtil::UUID_PATTERN,
                    ],
                    'defaults' => [
                        'controller' => Controller\FileController::class,
                        'method' => 'GET',
                        'action' => 'getFileList',
                        'access' => [
                            // SET ACCESS CONTROL
                        ],
                    ],
                ],
            ],
            'filelistingstatus' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/app/:appId/file/status/:workflowStatus',
                    'constraints' => [
                        'appId' => UuidUtil::UUID_PATTERN,
                    ],
                    'defaults' => [
                        'controller' => Controller\FileController::class,
                        'method' => 'GET',
                        'action' => 'getFileList',
                        'access' => [
                            // SET ACCESS CONTROL
                        ],
                    ],
                ],
            ],
            'filelistingcommand' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/app/:appId/file/command/:commands',
                    'constraints' => [
                        'appId' => UuidUtil::UUID_PATTERN,
                    ],
                    'defaults' => [
                        'controller' => Controller\FileController::class,
                        'method' => 'GET',
                        'action' => 'getFileListCommand',
                        'access' => [
                            // SET ACCESS CONTROL
                        ],
                    ],
                ],
            ],
            'filelistinguser' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/app/:appId/file/user/:userId[/status/:workflowStatus][/entity/:entityName][/assoc/:assocId]',
                    'constraints' => [
                        'appId' => UuidUtil::UUID_PATTERN,
                    ],
                    'defaults' => [
                        'controller' => Controller\FileController::class,
                        'method' => 'GET',
                        'action' => 'getFileList',
                        'access' => [
                            // SET ACCESS CONTROL
                        ],
                    ],
                ],
            ],
            'filelistfilter' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/app/:appId/file/search[/status/:workflowStatus][/entity/:entityName][/assoc/:assocId][/created[/gte/:gtCreatedDate][/lte/:ltCreatedDate]][/createdBy/:createdBy]',
                    'defaults' => [
                        'controller' => Controller\FileController::class,
                        'method' => 'GET',
                        'action' => 'getFileList',
                        'access' => [
                            // SET ACCESS CONTROL
                        ],
                    ],
                ],
            ],
            'app_error_log' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/app/:appId/errorlog',
                    'defaults' => [
                        'controller' => Controller\ErrorLogController::class,
                        'method' => 'GET',
                        'access' => [
                            // SET ACCESS CONTROL
                        ],
                    ],
                ],
            ],
            'app_error_retry' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/app/:appId/errorlog/:errorId/retry',
                    'defaults' => [
                        'controller' => Controller\ErrorLogController::class,
                        'method' => 'GET',
                        'action' => 'retry',
                        'access' => [
                            // SET ACCESS CONTROL
                        ],
                    ],
                ],
            ],
            'app_userlist' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/app/:appId/account/:accountId/userlist',
                    'defaults' => [
                        'controller' => Controller\AppDelegateController::class,
                        'method' => 'GET',
                        'action' => 'userlist',
                        'access' => [
                            // SET ACCESS CONTROL
                        ],
                    ],
                ],
            ],
            'fileCRUD' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/app/:appId/file/:id/data',
                    'constraints' => [
                        'appId' => UuidUtil::UUID_PATTERN,
                        'fileId' => UuidUtil::UUID_PATTERN,
                    ],
                    'defaults' => [
                        'controller' => Controller\FileController::class,
                        'method' => 'GET',
                        'access' => [
                            // SET ACCESS CONTROL
                        ],
                    ],
                ],
            ],
            'filedocumentlisting' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/app/:appId/file/:fileId/document',
                    'constraints' => [
                        'appId' => UuidUtil::UUID_PATTERN,
                        'fileId' => UuidUtil::UUID_PATTERN,
                    ],
                    'defaults' => [
                        'controller' => Controller\FileController::class,
                        'method' => 'GET',
                        'action' => 'getFileDocumentList',
                        'access' => [
                            // SET ACCESS CONTROL
                        ],
                    ],
                ],
            ],
            'getFileDocuments' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/app/:appId/document/:document',
                    'constraints' => [
                        'appId' => UuidUtil::UUID_PATTERN,
                    ],
                    'defaults' => [
                        'controller' => Controller\DocumentController::class,
                        'method' => 'GET',
                        'access' => [
                            // SET ACCESS CONTROL
                        ],
                    ],
                ],
            ],
            'file_document_get' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '[/:appId]/:accountId/:fileId[/:folder]/:document',
                    'constraints' => [
                        'appId' => UuidUtil::UUID_PATTERN,
                        'accountId' => UuidUtil::UUID_PATTERN,
                        'fileId' => UuidUtil::UUID_PATTERN,
                    ],
                    'defaults' => [
                        'controller' => Controller\DocumentController::class,
                        'method' => 'GET',
                        'access' => [
                            // SET ACCESS CONTROL
                        ],
                    ],
                ],
            ],
            'pipeline_execute' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/app/:appId/pipeline[/commands/:commands]',
                    'constraints' => [
                        'appId' => UuidUtil::UUID_PATTERN,
                    ],
                    'defaults' => [
                        'controller' => Controller\PipelineController::class,
                        'method' => 'GET',
                        'action' => 'executePipeline',
                        'access' => [
                            // SET ACCESS CONTROL
                        ],
                    ],
                ],
            ],
            'pipeline_batch_execute' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/app/:appId/pipeline/batchprocess',
                    'constraints' => [
                        'appId' => UuidUtil::UUID_PATTERN,
                    ],
                    'defaults' => [
                        'controller' => Controller\PipelineController::class,
                        'method' => 'POST',
                        'action' => 'executeBatchPipeline',
                        'access' => [
                            // SET ACCESS CONTROL
                        ],
                    ],
                ],
            ],
            'commands_execute' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/app/:appId/commands',
                    'constraints' => [
                        'appId' => UuidUtil::UUID_PATTERN,
                    ],
                    'defaults' => [
                        'controller' => Controller\CommandController::class,
                        'method' => 'POST',
                        'action' => 'executeCommands',
                        'access' => [
                            // SET ACCESS CONTROL
                        ],
                    ],
                ],
            ],
            'scheduleJob' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/app/:appId/scheduleJob',
                    'constraints' => [
                        'appId' => UuidUtil::UUID_PATTERN,
                    ],
                    'defaults' => [
                        'controller' => Controller\JobController::class,
                        'method' => 'POST',
                        'action' => 'scheduleJob',
                        'access' => [
                            // SET ACCESS CONTROL
                        ],
                    ],
                ],
            ],
            'getJobsList' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/app/:appId/getJobsList',
                    'constraints' => [
                        'appId' => UuidUtil::UUID_PATTERN,
                    ],
                    'defaults' => [
                        'controller' => Controller\JobController::class,
                        'method' => 'GET',
                        'action' => 'getJobsList',
                        'access' => [
                            // SET ACCESS CONTROL
                        ],
                    ],
                ],
            ],
            'getJob' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/app/:appId/getJob/:jobId',
                    'constraints' => [
                        'appId' => UuidUtil::UUID_PATTERN,
                    ],
                    'defaults' => [
                        'controller' => Controller\JobController::class,
                        'method' => 'GET',
                        'action' => 'getJobDetails',
                        'access' => [
                            // SET ACCESS CONTROL
                        ],
                    ],
                ],
            ],
            'cancelJob' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/app/:appId/cancelJob',
                    'constraints' => [
                        'appId' => UuidUtil::UUID_PATTERN,
                    ],
                    'defaults' => [
                        'controller' => Controller\JobController::class,
                        'method' => 'POST',
                        'action' => 'cancelJob',
                        'access' => [
                            // SET ACCESS CONTROL
                        ],
                    ],
                ],
            ],
            'cancelJobId' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/app/:appId/cancelJobId',
                    'constraints' => [
                        'appId' => UuidUtil::UUID_PATTERN,
                    ],
                    'defaults' => [
                        'controller' => Controller\JobController::class,
                        'method' => 'POST',
                        'action' => 'cancelJobId',
                        'access' => [
                            // SET ACCESS CONTROL
                        ],
                    ],
                ],
            ],
            'attachmentUpload' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/app/:appId/file/attachment',
                    'constraints' => [
                        'appId' => UuidUtil::UUID_PATTERN,
                    ],
                    'defaults' => [
                        'controller' => Controller\FileAttachmentController::class,
                        'method' => 'POST',
                        'action' => 'addAttachment',
                        'access' => [
                            // SET ACCESS CONTROL
                        ],
                    ],
                ],
            ],
            'attachmentRemoval' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/app/:appId/file/:fileId/attachment/:attachmentId/remove',
                    'constraints' => [
                        'appId' => UuidUtil::UUID_PATTERN,
                        'fileId' => UuidUtil::UUID_PATTERN,
                        'attachmentId' => UuidUtil::UUID_PATTERN,
                    ],
                    'defaults' => [
                        'controller' => Controller\FileAttachmentController::class,
                        'method' => 'DELETE',
                        'action' => 'removeAttachment',
                        'access' => [
                            // SET ACCESS CONTROL
                        ],
                    ],
                ],
            ],
            'attachmentRename' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/app/:appId/file/:fileId/attachment/:attachmentId',
                    'constraints' => [
                        'appId' => UuidUtil::UUID_PATTERN,
                        'fileId' => UuidUtil::UUID_PATTERN,
                        'attachmentId' => UuidUtil::UUID_PATTERN,
                    ],
                    'defaults' => [
                        'controller' => Controller\FileAttachmentController::class,
                        'method' => 'POST',
                        'action' => 'renameAttachment',
                        'access' => [
                            // SET ACCESS CONTROL
                        ],
                    ],
                ],
            ],
            'reindexfile' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/app/:appId/file/reindex',
                    'constraints' => [
                        'appId' => UuidUtil::UUID_PATTERN,
                    ],
                    'defaults' => [
                        'controller' => Controller\FileController::class,
                        'method' => 'GET',
                        'action' => 'reIndex',
                        'access' => [
                            // SET ACCESS CONTROL
                        ],
                    ],
                ],
            ],
            'getCssFile' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/app/:appId/:indexType',
                    'constraints' => [
                        'appId' => UuidUtil::UUID_PATTERN,
                        'indexType' => 'cssFile|jsIndex',
                    ],
                    'defaults' => [
                        'controller' => Controller\AppController::class,
                        'method' => 'GET',
                        'action' => 'getCssFile',
                    ],
                ],
            ],
            'getArtifacts' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/app/:appUuid/artifact/list/:artifactType',
                    'constraints' => [
                        'appUuid' => UuidUtil::UUID_PATTERN,
                        'artifactType' => 'form|workflow|delegate|template|appupgrade|transformer|migrations|component',
                    ],
                    'defaults' => [
                        'controller' => Controller\AppArtifactController::class,
                        'method' => 'GET',
                        'action' => 'getArtifacts',
                        'access' => [
                            // SET ACCESS CONTROL
                        ],
                    ],
                ],
            ],
            'addArtifact' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/app/:appUuid/artifact/add/:artifactType',
                    'constraints' => [
                        'appUuid' => UuidUtil::UUID_PATTERN,
                        'artifactType' => 'form|workflow|app_icon|app_icon_white|delegate|template|appupgrade|transformer|migrations|component',
                    ],
                    'defaults' => [
                        'controller' => Controller\AppArtifactController::class,
                        'method' => 'POST',
                        'action' => 'addArtifact',
                        'access' => [
                            // SET ACCESS CONTROL
                        ],
                    ],
                ],
            ],
            'deleteArtifact' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/app/:appUuid/artifact/delete/:artifactType/:artifactName',
                    'constraints' => [
                        'appUuid' => UuidUtil::UUID_PATTERN,
                        'artifactType' => 'form|workflow|delegate|template|appupgrade|transformer|migrations|component',
                    ],
                    'defaults' => [
                        'controller' => Controller\AppArtifactController::class,
                        'method' => 'DELETE',
                        'action' => 'deleteArtifact',
                        'access' => [
                            // SET ACCESS CONTROL
                        ],
                    ],
                ],
            ],
            'downloadAppArchive' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/app/:appUuid/archive/download',
                    'constraints' => [
                        'appUuid' => UuidUtil::UUID_PATTERN,
                    ],
                    'defaults' => [
                        'controller' => Controller\AppArtifactController::class,
                        'method' => 'GET',
                        'action' => 'downloadAppArchive',
                        'access' => [
                            // SET ACCESS CONTROL
                        ],
                    ],
                ],
            ],
            'uploadAppArchive' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/app/archive/upload',
                    'defaults' => [
                        'controller' => Controller\AppArtifactController::class,
                        'method' => 'POST',
                        'action' => 'uploadAppArchive',
                        'access' => [
                            // SET ACCESS CONTROL
                        ],
                    ],
                ],
            ],
            'getAppProperties' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/app/:appId/account/:accountId/appProperties',
                    'constraints' => [
                        'appId' => UuidUtil::UUID_PATTERN,
                        'accountId' => UuidUtil::UUID_PATTERN,
                    ],
                    'defaults' => [
                        'controller' => Controller\AppRegisterController::class,
                        'method' => 'GET',
                        'action' => 'getAppProperties',
                        'access' => [
                            // SET ACCESS CONTROL
                        ],
                    ],
                ],
            ],
            'getAccountOnServiceType' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/app/:appId/getAccounts/:serviceType',
                    'constraints' => [
                        'appId' => UuidUtil::UUID_PATTERN,
                        'serviceType' => 'forInstall|Installed',
                    ],
                    'defaults' => [
                        'controller' => Controller\AppRegisterController::class,
                        'method' => 'GET',
                        'action' => 'getAccountOnServiceType',
                        'access' => [
                            // 'get' => ['MANAGE_ACCOUNT_READ','MANAGE_INSTALL_APP_READ'],
                        ],
                    ],
                ],
            ],
            'downloadAppFile' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/app/:appUuid/download/:artifactType/:artifactName',
                    'constraints' => [
                        'appUuid' => UuidUtil::UUID_PATTERN,
                        'artifactType' => 'delegate|template|appupgrade|transformer|migrations|component',
                    ],
                    'defaults' => [
                        'controller' => Controller\AppArtifactController::class,
                        'method' => 'GET',
                        'action' => 'downloadAppFile',
                        'access' => [
                            // SET ACCESS CONTROL
                        ],
                    ],
                ],
            ],
            'appBusinessRoles' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/app/:appId/appBusinessRoles',
                    'defaults' => [
                        'controller' => Controller\AppController::class,
                        'method' => 'GET',
                        'action' => 'getAppBusinessRoles',
                        'access' => [
                            // SET ACCESS CONTROL
                        ],
                    ],
                ],
            ],
        ],
    ],
    'view_manager' => [
        // We need to set this up so that we're allowed to return JSON
        // responses from our controller.
        'strategies' => ['ViewJsonStrategy'],
    ],
];
