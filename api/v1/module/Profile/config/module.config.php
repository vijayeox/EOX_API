<?php

namespace Profile;

use Zend\Router\Http\Segment;

return [
    'router' => [
        'routes' => [
            'profile' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/profile[/:profileId]',
                    'defaults' => [
                        'controller' => Controller\ProfileController::class,
                        'access' => [
                            // SET ACCESS CONTROL
                            'put' => 'MANAGE_PROFILE_WRITE',
                            'post' => 'MANAGE_PROFILE_WRITE',
                            'delete' => 'MANAGE_PROFILE_WRITE'
                        ],
                    ],
                ],
            ],
            'profileUser' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/profile/user[/:userId]',
                    'defaults' => [
                        'controller' => Controller\ProfileController::class,
                        'method' => 'GET',
                        'action' => 'getProfileforUser',
                        'access' => [
                            'getProfileforUser' => 'MANAGE_PROFILE_READ',
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
