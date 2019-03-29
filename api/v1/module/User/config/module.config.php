<?php

namespace User;

use Zend\Log\Logger;
use Zend\Router\Http\Segment;
use Zend\Router\Http\Method;
use Zend\Log\Formatter\Simple;
use Zend\Log\Filter\Priority;
use Zend\Log\Processor\RequestId;

return [
    'router' => [
        'routes' => [
            'user' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/user[/:userId][/type][/:typeId]',
                    'defaults' => [
                        'controller' => Controller\UserController::class,
                        'access' => [
                            // SET ACCESS CONTROL
                            'put' => 'MANAGE_USER_WRITE',
                            'post' => 'MANAGE_USER_WRITE',
                            'delete' => 'MANAGE_USER_WRITE',
                            'get' => 'MANAGE_USER_READ',
                        ],
                    ],
                ],
            ],
            'loggedInUser' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/user/me[/type][/:typeId]',
                    'defaults' => [
                        'controller' => Controller\UserController::class,
                        'method' => 'GET',
                        'action' => 'getUserDetail',
                        'access' => [
                            // SET ACCESS CONTROL
                        ],
                    ],
                ],
            ],
            'assignUserManager' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/user/:userId/assign/:managerId',
                    'defaults' => [
                        'controller' => Controller\UserController::class,
                        'method' => 'GET',
                        'action' => 'assignManagerToUser',
                        'access' => [
                            // SET ACCESS CONTROL
                            'assignManagerToUser' => 'MANAGE_USER_WRITE',
                        ],
                    ],
                ],
            ],
            'getUserAppsAndPrivileges' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/user/me/access',
                    'defaults' => [
                        'controller' => Controller\UserController::class,
                        'method' => 'GET',
                        'action' => 'getUserAppsAndPrivileges',
                        'access' => [
                            // SET ACCESS CONTROL
                            'getUserAppsAndPrivileges' => 'MANAGE_USER_WRITE',
                        ],
                    ],
                ],
            ],
            'removeUserManager' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/user/:userId/remove/:managerId',
                    'defaults' => [
                        'controller' => Controller\UserController::class,
                        'method' => 'DELETE',
                        'action' => 'removeManagerForUser',
                        'access' => [
                            // SET ACCESS CONTROL
                            'removeManagerForUser' => 'MANAGE_USER_WRITE',
                        ],
                    ],
                ],
            ],
            'addUserToGroup' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/user/:userId/addusertogroup/:groupId',
                    'defaults' => [
                        'controller' => Controller\UserController::class,
                        'method' => 'POST',
                        'action' => 'addUserToGroup',
                        'access' => [
                            // SET ACCESS CONTROL
                            'addUserToGroup' => 'MANAGE_USER_WRITE',
                        ],
                    ],
                ],
            ],
            'addOrganizationToUser' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/user/:userId/organization/:organizationId',
                    'defaults' => [
                        'controller' => Controller\UserController::class,
                        'method' => 'POST',
                        'action' => 'addOrganizationToUser',
                        'access' => [
                            // SET ACCESS CONTROL
                            'addOrganizationToUser' => 'MANAGE_USER_WRITE',
                        ],
                    ],
                ],
            ],
            'removeUserFromGroup' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/user/:userId/removeuserfromgroup',
                    'defaults' => [
                        'controller' => Controller\UserController::class,
                        'method' => 'DELETE',
                        'action' => 'removeUserFromGroup',
                        'access' => [
                            // SET ACCESS CONTROL
                            'removeUserFromGroup' => 'MANAGE_USER_WRITE',
                        ],
                    ],
                ],
            ],
            'addUserToProject' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/user/:userId/addusertoproject/:projectId',
                    'defaults' => [
                        'controller' => Controller\UserController::class,
                        'method' => 'POST',
                        'action' => 'addUserToProject',
                        'access' => [
                            // SET ACCESS CONTROL
                            'addUserToProject' => 'MANAGE_USER_WRITE',
                        ],
                    ],
                ],
            ],
            'removeUserFromProject' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/user/:userId/removeuserfromproject/:projectId',
                    'defaults' => [
                        'controller' => Controller\UserController::class,
                        'method' => 'DELETE',
                        'action' => 'removeUserFromProject',
                        'access' => [
                            // SET ACCESS CONTROL
                            'removeUserFromProject' => 'MANAGE_USER_WRITE',
                        ],
                    ],
                ],
            ],
            'userToken' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/user/usertoken',
                    'defaults' => [
                        'controller' => Controller\UserController::class,
                        'method' => 'get',
                        'action' => 'userLoginToken',
                        'access' => [
                            // SET ACCESS CONTROL
                            'userLoginToken' => 'MANAGE_USER_READ',
                        ],
                    ],
                ],
            ],
            'userSearch' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/user/usersearch',
                    'defaults' => [
                        'controller' => Controller\UserController::class,
                        'method' => 'POST',
                        'action' => 'userSearch'
                    ],
                ],
            ],
            'changePassword' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/user/me/changepassword',
                    'defaults' => [
                        'controller' => Controller\UserController::class,
                        'method' => 'POST',
                        'action' => 'changePassword'
                    ],
                ],
            ],
            'profilePicture' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/user/profile/:profileId',
                    'defaults' => [
                        'controller' => Controller\ProfilePictureDownloadController::class
                    ],
                ],
            ],
            'updateProfile' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/user/profile',
                    'defaults' => [
                        'controller' => Controller\ProfilePictureController::class,
                        'method' => 'POST',
                        'action' => 'updateProfile'
                    ],
                ],
            ],
            'forgotPassword' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/user/me/forgotpassword',
                    'defaults' => [
                        'controller' => Controller\UserController::class,
                        'method' => 'POST',
                        'action' => 'forgotPassword'
                    ],
                ],
            ],
            'updateNewPassword' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/user/me/updatenewpassword',
                    'defaults' => [
                        'controller' => Controller\UserController::class,
                        'method' => 'POST',
                        'action' => 'updateNewPassword'
                    ],
                ],
            ],
            'getSession' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/user/me/getsession',
                    'defaults' => [
                        'controller' => Controller\UserSessionController::class,
                        'method' => 'GET',
                        'action' => 'getSession'
                    ],
                ],
            ],
            'updateSession' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/user/me/updatesession',
                    'defaults' => [
                        'controller' => Controller\UserSessionController::class,
                        'method' => 'POST',
                        'action' => 'updateSession'
                    ],
                ],
            ],
        ],
    ],
    'log' => [
        'UserLogger' => [
            'writers' => [
                'stream' => [
                    'name' => 'stream',
                    'priority' => \Zend\Log\Logger::ALERT,
                    'options' => [
                        'stream' => __DIR__ . '/../../../logs/user.log',
                        'formatter' => [
                            'name' => \Zend\Log\Formatter\Simple::class,
                            'options' => [
                                'format' => '%timestamp% %priorityName% (%priority%): %message% %extra%', 'dateTimeFormat' => 'c',
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