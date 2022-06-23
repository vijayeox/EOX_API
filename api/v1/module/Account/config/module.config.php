<?php
namespace Account;

use Zend\Router\Http\Segment;

return [
    'router' => [
        'routes' => [
            'account' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/account[/:accountId]',
                    'defaults' => [
                        'controller' => Controller\AccountController::class,
                        'access' => [
                            // SET ACCESS CONTROL
                            'put' => 'MANAGE_ACCOUNT_WRITE',
                            'post' => 'MANAGE_ACCOUNT_WRITE',
                            'delete' => 'MANAGE_ACCOUNT_WRITE',
                            'get' => ['MANAGE_ACCOUNT_READ','MANAGE_INSTALL_APP_READ'],
                        ],
                    ],
                ],
            ],
            'addUsersToAccount' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/account/:accountId/save',
                    'defaults' => [
                        'controller' => Controller\AccountController::class,
                        'method' => 'POST',
                        'action' => 'addUsersToAccount',
                        'access' => [
                            // SET ACCESS CONTROL
                            'addUsersToAccount' => 'MANAGE_ACCOUNT_WRITE',
                        ],
                    ],
                ],
            ],
            'accountLogo' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/account/logo/:accountId',
                    'defaults' => [
                        'controller' => Controller\AccountLogoController::class,
                    ],
                ],
            ],
            'accountuser' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/account/:accountId/users',
                    'defaults' => [
                        'controller' => Controller\AccountController::class,
                        'method' => 'GET',
                        'action' => 'getListOfAccountUsers',
                        'access' => [
                            'getListOfAccountUsers' => ['MANAGE_USER_READ'],
                        ],
                    ],
                ],
            ],
            'checkAccountUser' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/account/:accountId/usercheck/:username',
                    'defaults' => [
                        'controller' => Controller\AccountController::class,
                        'method' => 'GET',
                        'action' => 'checkAccountUser',
                        'access' => [
                            'checkAccountUser' => ['MANAGE_USER_WRITE'], //Since this check is done before writing and should be done by an admin
                        ],
                    ],
                ],
            ],
            'getListofAdminUsers' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/account[/:accountId]/adminusers',
                    'defaults' => [
                        'controller' => Controller\AccountController::class,
                        'method' => 'GET',
                        'action' => 'getListofAdminUsers',
                        'access' => [
                            'getListofAdminUsers' => ['MANAGE_ACCOUNT_READ', 'MANAGE_MYACCOUNT_WRITE'],
                        ],
                    ],
                ],
            ],
            'getListofAccountTeams' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/account/:accountId/teams',
                    'defaults' => [
                        'controller' => Controller\AccountController::class,
                        'method' => 'GET',
                        'action' => 'getListofAccountTeams',
                        'access' => [
                            'getListofAccountTeams' => ['MANAGE_TEAM_READ'],
                        ],
                    ],
                ],
            ],
            'getListofAccountProjects' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/account/:accountId/projects',
                    'defaults' => [
                        'controller' => Controller\AccountController::class,
                        'method' => 'GET',
                        'action' => 'getListofAccountProjects',
                        'access' => [
                            'getListofAccountProjects' => ['MANAGE_PROJECT_READ'],
                        ],
                    ],
                ],
            ],
            'getListofAccountAnnouncements' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/account/:accountId/announcements',
                    'defaults' => [
                        'controller' => Controller\AccountController::class,
                        'method' => 'GET',
                        'action' => 'getListofAccountAnnouncements',
                        'access' => [
                            'getListofAccountAnnouncements' => ['MANAGE_ANNOUNCEMENT_READ'],
                        ],
                    ],
                ],
            ],
            'getListofAccountRoles' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/account/:accountId/roles',
                    'defaults' => [
                        'controller' => Controller\AccountController::class,
                        'method' => 'GET',
                        'action' => 'getListofAccountRoles',
                        'access' => [
                            'getListofAccountRoles' => ['MANAGE_ROLE_READ'],
                        ],
                    ],
                ],
            ],
            'getOwner' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/account/subordinate[/:managerId]',
                    'defaults' => [
                        'controller' => Controller\AccountController::class,
                        'method' => 'GET',
                        'action' => 'getSubordinates',
                        'access' => [
                            'getSubordinates' => ['MANAGE_ACCOUNT_READ'],
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
