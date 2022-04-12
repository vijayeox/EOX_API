<?php

namespace Integrations;

use Zend\Router\Http\Segment;

return [
    'router' => [
        'routes' => [
            'overdrive' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/overdrive[/:action]',
                    'defaults' => [
                        'controller' => Controller\OverdriveapiController::class,
                        'method' => 'GET',
                        'action' => 'getContractor',
                        'access' => [
                            
                        ],
                    ],

                    'route' => '/overdrive[/:action]',
                    'defaults' => [
                        'controller' => Controller\OverdriveapiController::class,
                        'method' => 'PUT',
                        'action' => 'addContractor',
                        'access' => [
                            
                        ],
                    ],

                    'route' => '/overdrive[/:action]',
                    'defaults' => [
                        'controller' => Controller\OverdriveapiController::class,
                        'method' => 'PUT',
                        'action' => 'addDriver',
                        'access' => [
                            
                        ],
                    ],
                    'route' => '/overdrive[/:action]',
                    'defaults' => [
                        'controller' => Controller\OverdriveapiController::class,
                        'method' => 'POST',
                        'action' => 'tchoiceRegistration',
                        'access' => [
                            
                        ],
                    ],
                ],
            ],
            'triumph_delta' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/integration/triumph',
                    'defaults' => [
                        'controller' => Controller\TriumphController::class,
                        'method' => 'POST',
                        'action' => 'testEndpoint',
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
