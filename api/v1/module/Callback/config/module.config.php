<?php
namespace Callback;

use Zend\Log\Logger;
use Zend\Router\Http\Segment;
use Zend\Log\Formatter\Simple;
use Zend\Log\Filter\Priority;
use Zend\Log\Processor\RequestId;

return [
    'router' => [
        'routes' => [
            'user_added_mail' => [
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/callback/ox/createuser',
                    'defaults' => [
                        'controller' => Controller\OXCallbackController::class,
                        'action' => 'userCreated',
                    ],
                ],
            ],
            'crmaddcontactcallback' => [
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/callback/crm/addcontact',
                    'defaults' => [
                        'controller' => Controller\CRMCallbackController::class,
                        'action' => 'addContact',
                    ],
                ],
            ],
            'calendarsendmailcallback' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/callback/calendar/sendmail',
                    'defaults' => [
                        'controller' => Controller\CalendarCallbackController::class,
                        'action' => 'sendMail',
                    ],
                ],
            ],
            'calendaraddeventcallback' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/callback/calendar/addevent',
                    'defaults' => [
                        'controller' => Controller\CalendarCallbackController::class,
                        'action' => 'addEvent',
                    ],
                ],
            ],
            'addcallback' => [
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/callback/chat/addorg',
                    'defaults' => [
                        'controller' => Controller\ChatCallbackController::class,
                        'action' => 'addOrg',
                    ],
                ],
            ],
            'updatecallback' => [
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/callback/chat/updateorg',
                    'defaults' => [
                        'controller' => Controller\ChatCallbackController::class,
                        'action' => 'updateOrg',
                    ],
                ],
            ],
            'deletecallback' => [
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/callback/chat/deleteorg',
                    'defaults' => [
                        'controller' => Controller\ChatCallbackController::class,
                        'action' => 'deleteOrg',
                    ],
                ],
            ],
            'addusercallback' => [
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/callback/chat/adduser',
                    'defaults' => [
                        'controller' => Controller\ChatCallbackController::class,
                        'action' => 'addUser',
                    ],
                ],
            ],
            'removeusercallback' => [
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/callback/chat/removeuser',
                    'defaults' => [
                        'controller' => Controller\ChatCallbackController::class,
                        'action' => 'removeUser',
                    ],
                ],
            ],
            'createchannelcallback' => [
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/callback/chat/createchannel',
                    'defaults' => [
                        'controller' => Controller\ChatCallbackController::class,
                        'action' => 'createChannel',
                    ],
                ],
            ],
            'updatechannelcallback' => [
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/callback/chat/updatechannel',
                    'defaults' => [
                        'controller' => Controller\ChatCallbackController::class,
                        'action' => 'updateChannel',
                    ],
                ],
            ],
            'deletechannelcallback' => [
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/callback/chat/deletechannel',
                    'defaults' => [
                        'controller' => Controller\ChatCallbackController::class,
                        'action' => 'deleteChannel',
                    ],
                ],
            ],
            'addusertochannelcallback' => [
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/callback/chat/addusertochannel',
                    'defaults' => [
                        'controller' => Controller\ChatCallbackController::class,
                        'action' => 'adduserToChannel',
                    ],
                ],
            ],
            'removeuserfromchannelcallback' => [
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/callback/chat/removeuserfromchannel',
                    'defaults' => [
                        'controller' => Controller\ChatCallbackController::class,
                        'action' => 'removeUserFromChannel',
                    ],
                ],
            ],
            'addprojectfromcallback' => [
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/callback/task/addproject',
                    'defaults' => [
                        'controller' => Controller\TaskCallbackController::class,
                        'action' => 'addProject',
                    ],
                ],
            ],
            'deleteprojectfromcallback' => [
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/callback/task/deleteproject',
                    'defaults' => [
                        'controller' => Controller\TaskCallbackController::class,
                        'action' => 'deleteProject',
                    ],
                ],
            ],
            'updateprojectfromcallback' => [
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/callback/task/updateproject',
                    'defaults' => [
                        'controller' => Controller\TaskCallbackController::class,
                        'action' => 'updateProject',
                    ],
                ],
            ],
            'ttadduserfromcallback' => [
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/callback/task/addusertotasktracker',
                    'defaults' => [
                        'controller' => Controller\TaskCallbackController::class,
                        'action' => 'createUser',
                    ],
                ],
            ],
            'ttdeleteuserfromcallback' => [
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/callback/task/deleteuserfromtasktracker',
                    'defaults' => [
                        'controller' => Controller\TaskCallbackController::class,
                        'action' => 'deleteUser',
                    ],
                ],
            ],
        ],
    ],
    'log' => [
        'CallbackLogger' => [
            'writers' => [
                'stream' => [
                    'name' => 'stream',
                    'priority' => \Zend\Log\Logger::ALERT,
                    'options' => [
                        'stream' => __DIR__ . '/../../../logs/callback.log',
                        'formatter' => [
                            'name' => \Zend\Log\Formatter\Simple::class,
                            'options' => [
                                'format' => '%timestamp% %priorityName% (%priority%): %message% %extra%','dateTimeFormat' => 'c',
                            ],
                        ],
                        'filters' => [
                            'priority' => \Zend\Log\Logger::INFO,],
                        ],
                    ],
                ],
                'processors' => [
                    'requestid' => [
                        'name' => \Zend\Log\Processor\RequestId::class,],
                    ],
                ],
            ],
            'view_manager' => [
                // We need to set this up so that we're allowed to return JSON
                // responses from our controller.
                'strategies' => ['ViewJsonStrategy',],
            ],
        ];