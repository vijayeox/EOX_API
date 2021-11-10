<?php

namespace Kra;

use Zend\Router\Http\Segment;

return [
    'router' => [
        'routes' => [
            'kras' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/[account/:accountId/]kra[/:kraId]',
                    'defaults' => [
                        'controller' => Controller\KraController::class,
                        'access' => [
                            // SET ACCESS CONTROL
                            'put' => 'MANAGE_KRA_WRITE',
                            'post' => 'MANAGE_KRA_WRITE',
                            'delete' => 'MANAGE_KRA_WRITE'
                        ],
                    ],
                ],
            ],
            'krasUser' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/kra/user/:userId',
                    'defaults' => [
                        'controller' => Controller\KraController::class,
                        'method' => 'GET',
                        'action' => 'getKrasforUser',
                        'access' => [
                            'getKrasforUser' => 'MANAGE_KRA_WRITE',
                        ],
                    ],
                ],
            ],
            'krasBusinessRole' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/kra/brole/:businessRole',
                    'defaults' => [
                        'controller' => Controller\KraController::class,
                        'method' => 'GET',
                        'action' => 'getKrasforBusinessRole',
                        'access' => [
                            'getKrasforBusinessRole' => 'MANAGE_KRA_WRITE',
                        ],
                    ],
                ],
            ],
            'krasList' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/[account/:accountId/]kras/list',
                    'defaults' => [
                        'controller' => Controller\KraController::class,
                        'method' => 'POST',
                        'action' => 'krasList',
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
