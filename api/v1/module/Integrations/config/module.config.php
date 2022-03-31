<?php

namespace Integrations;

use Zend\Router\Http\Segment;

return [
    'router' => [
        'routes' => [
            'overdrive' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/getContractor',
                    'defaults' => [
                        'controller' => Controller\OverdriveapiController::class,
                        'method' => 'GET',
                        'action' => 'getContractor',
                        'access' => [
                            
                        ],
                    ],

                    'route' => '/addContractor',
                    'defaults' => [
                        'controller' => Controller\OverdriveapiController::class,
                        'method' => 'PUT',
                        'action' => 'addContractor',
                        'access' => [
                            
                        ],
                    ],

                    'route' => '/addDriver',
                    'defaults' => [
                        'controller' => Controller\OverdriveapiController::class,
                        'method' => 'PUT',
                        'action' => 'addDriver',
                        'access' => [
                            
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
