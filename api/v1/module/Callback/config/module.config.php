<?php
namespace Callback;

use Zend\Router\Http\Segment;

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
                    'route'    => '/callback/chat/addaccount',
                    'defaults' => [
                        'controller' => Controller\ChatCallbackController::class,
                        'action' => 'addAccount',
                    ],
                ],
            ],
            'savebotcallback' => [
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/callback/chat/savebot',
                    'defaults' => [
                        'controller' => Controller\ChatCallbackController::class,
                        'action' => 'saveBot',
                    ],
                ],
            ],
            'postfilecommentcallback' => [
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/callback/chat/postfilecomment',
                    'defaults' => [
                        'controller' => Controller\ChatCallbackController::class,
                        'action' => 'postFileComment',
                    ],
                ],
            ],
            'disablebotcallback' => [
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/callback/chat/disablebot',
                    'defaults' => [
                        'controller' => Controller\ChatCallbackController::class,
                        'action' => 'disableBot',
                    ],
                ],
            ],
            'appbotnotification' => [
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/callback/chat/appbotnotification',
                    'defaults' => [
                        'controller' => Controller\ChatCallbackController::class,
                        'action' => 'appBotNotification',
                    ],
                ],
            ],
            'updatecallback' => [
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/callback/chat/updateaccount',
                    'defaults' => [
                        'controller' => Controller\ChatCallbackController::class,
                        'action' => 'updateAccount',
                    ],
                ],
            ],
            'deletecallback' => [
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/callback/chat/deleteaccount',
                    'defaults' => [
                        'controller' => Controller\ChatCallbackController::class,
                        'action' => 'deleteAccount',
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
            'createbotcallback' => [
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/callback/chat/createbot',
                    'defaults' => [
                        'controller' => Controller\ChatCallbackController::class,
                        'action' => 'createBot',
                    ],
                ],
            ],
            'updatebotcallback' => [
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/callback/chat/updatebot',
                    'defaults' => [
                        'controller' => Controller\ChatCallbackController::class,
                        'action' => 'updateBot',
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
            'projectcreategroupfromcallback' => [
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/callback/task/creategroup',
                    'defaults' => [
                        'controller' => Controller\TaskCallbackController::class,
                        'action' => 'creategroup',
                    ],
                ],
            ],
            'projectupdategroupfromcallback' => [
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/callback/task/updategroup',
                    'defaults' => [
                        'controller' => Controller\TaskCallbackController::class,
                        'action' => 'updateGroup',
                    ],
                ],
            ],
            'projectdeletegroupfromcallback' => [
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/callback/task/deletegroup',
                    'defaults' => [
                        'controller' => Controller\TaskCallbackController::class,
                        'action' => 'deleteGroup',
                    ],
                ],
            ],
            'projectupdategroupusersfromcallback' => [
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/callback/task/updategroupusers',
                    'defaults' => [
                        'controller' => Controller\TaskCallbackController::class,
                        'action' => 'updateGroupUsers',
                    ],
                ],
            ],
            'sendsmsfromcallback' => [
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/callback/communication/sendsms',
                    'defaults' => [
                        'controller' => Controller\CommunicationCallbackController::class,
                        'action' => 'sendSms',
                    ],
                ],
            ],
            'makecallfromcallback' => [
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/callback/communication/makecall',
                    'defaults' => [
                        'controller' => Controller\CommunicationCallbackController::class,
                        'action' => 'makeCall',
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
