<?php
namespace Callback;

use Oxzion\Utils\UuidUtil;
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
                    'constraints' => [
                        'commentId' => UuidUtil::UUID_PATTERN
                    ],
                    'defaults' => [
                        'controller' => Controller\CommentController::class,
                    ],
                ],
            ],
            'putfilecommentupdate' => [
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/callback/chat/postfilecomment/:commentId',
                    'constraints' => [
                        'commentId' => UuidUtil::UUID_PATTERN
                    ],
                    'defaults' => [
                        'controller' => Controller\CommentController::class,
                        'method' => 'POST',
                        'action' => 'update',
                    ],
                ],
            ],
            'savecommentattachment' => [
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/comment/:commentId/saveCommentAttachment',
                    'constraints' => [
                        'commentId' => UuidUtil::UUID_PATTERN
                    ],
                    'defaults' => [
                        'controller' => Controller\CommentController::class,
                        'method' => 'POST',
                        'action' => 'saveCommentAttachment',
                    ],
                ],
            ],

            'putfilecommentgetattachment' => [
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/comment/:commentId/attachment/:fileName',
                    'constraints' => [
                        'commentId' => UuidUtil::UUID_PATTERN,
                    ],
                    'defaults' => [
                        'controller' => Controller\CommentController::class,
                        'method' => 'GET',
                        'action' => 'getCommentFile',
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
            'projectcreateteamfromcallback' => [
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/callback/task/createteam',
                    'defaults' => [
                        'controller' => Controller\TaskCallbackController::class,
                        'action' => 'createteam',
                    ],
                ],
            ],
            'projectupdateteamfromcallback' => [
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/callback/task/updateteam',
                    'defaults' => [
                        'controller' => Controller\TaskCallbackController::class,
                        'action' => 'updateTeam',
                    ],
                ],
            ],
            'projectdeleteteamfromcallback' => [
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/callback/task/deleteteam',
                    'defaults' => [
                        'controller' => Controller\TaskCallbackController::class,
                        'action' => 'deleteTeam',
                    ],
                ],
            ],
            'projectupdateteamusersfromcallback' => [
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/callback/task/updateteamusers',
                    'defaults' => [
                        'controller' => Controller\TaskCallbackController::class,
                        'action' => 'updateTeamUsers',
                    ],
                ],
            ],
            'updateProfilePicture' => [
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/callback/chat/updateprofilepicture',
                    'defaults' => [
                        'controller' => Controller\ChatCallbackController::class,
                        'action' => 'updateProfilePicture',
                    ],
                ],
            ],
            'saveExcel' => [
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/callback/document/saveexcel',
                    'defaults' => [
                        'controller' => Controller\DocumentCallbackController::class,
                        'action' => 'saveExcel',
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
