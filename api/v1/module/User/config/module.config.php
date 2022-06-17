<?php

namespace User;

use Zend\Router\Http\Segment;

return [
    'router' => [
        'routes' => [
            'user' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/[account/:accountId/]user[/:userId][/:type]',
                    'defaults' => [
                        'controller' => Controller\UserController::class,
                        'access' => [
                            // SET ACCESS CONTROL
                            'put' => ['MANAGE_USER_WRITE', 'MANAGE_ACCOUNT_WRITE', 'MANAGE_TEAM_WRITE'],
                            'post' => ['MANAGE_USER_WRITE', 'MANAGE_ACCOUNT_WRITE', 'MANAGE_TEAM_WRITE'],
                            'delete' => ['MANAGE_USER_WRITE', 'MANAGE_ACCOUNT_WRITE', 'MANAGE_TEAM_WRITE'],
                        ],
                    ],
                ],
            ],
            'loggedInUser' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/user/me[/:type]',
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
            'saveMe' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/user/me/save',
                    'defaults' => [
                        'controller' => Controller\UserController::class,
                        'method' => 'PUT',
                        'action' => 'saveMe',
                    ],
                ],
            ],
            'switchAccount' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/user/switchaccount',
                    'defaults' => [
                        'controller' => Controller\UserController::class,
                        'method' => 'POST',
                        'action' => 'switchAccount',
                    ],
                ],
            ],
            'userInfoById' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/user/:userId/detail/:type',
                    'defaults' => [
                        'controller' => Controller\UserController::class,
                        'method' => 'GET',
                        'action' => 'getUserInfoById',
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
            'addUserToTeam' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/user/:userId/addusertoteam/:teamId',
                    'defaults' => [
                        'controller' => Controller\UserController::class,
                        'method' => 'POST',
                        'action' => 'addUserToTeam',
                        'access' => [
                            // SET ACCESS CONTROL
                            'addUserToTeam' => 'MANAGE_USER_WRITE',
                        ],
                    ],
                ],
            ],
            'removeUserFromTeam' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/user/:userId/removeuserfromteam',
                    'defaults' => [
                        'controller' => Controller\UserController::class,
                        'method' => 'DELETE',
                        'action' => 'removeUserFromTeam',
                        'access' => [
                            // SET ACCESS CONTROL
                            'removeUserFromTeam' => 'MANAGE_USER_WRITE',
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
                        'action' => 'userSearch',
                    ],
                ],
            ],
            'usersList' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/[account/:accountId/]users/list',
                    'defaults' => [
                        'controller' => Controller\UserController::class,
                        'method' => 'POST',
                        'action' => 'usersList',
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
                        'action' => 'changePassword',
                    ],
                ],
            ],
            'profilePicture' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/user/profile/:profileId',
                    'defaults' => [
                        'controller' => Controller\ProfilePictureDownloadController::class,
                    ],
                ],
            ],
            'profilePictureByUsername' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/user/profile/username/:username',
                    'defaults' => [
                        'controller' => Controller\ProfilePictureDownloadController::class,
                        'action' => 'profilePictureByUsername',
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
                        'action' => 'updateProfile',
                    ],
                ],
            ],
            'forgotPassword' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/user/me/forgotpassword',
                    'defaults' => [
                        'controller' => Controller\ForgotPasswordController::class,
                        'method' => 'POST',
                        'action' => 'forgotPassword',
                    ],
                ],
            ],
            'resetPassword' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/user/me/resetpassword',
                    'defaults' => [
                        'controller' => Controller\ForgotPasswordController::class,
                        'method' => 'POST',
                        'action' => 'resetPassword',
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
                        'action' => 'getSession',
                    ],
                ],
            ],
            'getUserDetailList' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/account/:accountId/user/:userId/profile',
                    'defaults' => [
                        'controller' => Controller\UserController::class,
                        'method' => 'GET',
                        'action' => 'getUserDetailList',
                        'access' => [
                            'getUserDetailList' => ['MANAGE_USER_READ'],
                        ],
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
                        'action' => 'updateSession',
                    ],
                ],
            ],

            'get_loggedIn' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/user/me/hasLoggedIn',
                    'defaults' => [
                        'controller' => Controller\UserController::class,
                        'method' => 'GET',
                        'action' => 'hasLoggedIn',
                    ],
                ],
            ],

            'update_loggedIn' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/user/me/updateLoggedIn',
                    'defaults' => [
                        'controller' => Controller\UserController::class,
                        'method' => 'POST',
                        'action' => 'updateLoggedInStatus',
                    ],
                ],
            ],
            'getuserproject' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/user/:userId/project',
                    'defaults' => [
                        'controller' => Controller\UserController::class,
                        'method' => 'GET',
                        'action' => 'getuserproject',
                    ],
                ],
            ],
            'getupdatecache' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/user/me/getuserbrowsercachestatus',
                    'defaults' => [
                        'controller' => Controller\UserController::class,
                        'method' => 'GET',
                        'action' => 'getUserBrowserCacheStatus',
                    ],
                ],
            ],
            'setusercache' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/user/setuserbrowsercache',
                    'defaults' => [
                        'controller' => Controller\UserController::class,
                        'method' => 'PUT',
                        'action' => 'setUserBrowserCache',
                        'access' => [
                            // SET ACCESS CONTROL
                            'setUserBrowserCache' => 'MANAGE_USER_WRITE',
                        ],
                    ],
                ],
            ],
            'insert_activity' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/user/me/activity/:activity',
                    'defaults' => [
                        'controller' => Controller\UserController::class,
                        'method' => 'POST',
                        'action' => 'insertActivityTime',
                    ],
                ],
            ]
        ],
    ],
    'view_manager' => [
        // We need to set this up so that we're allowed to return JSON
        // responses from our controller.
        'strategies' => ['ViewJsonStrategy'],
    ],
];
