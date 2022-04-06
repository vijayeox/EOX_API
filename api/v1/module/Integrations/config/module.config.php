<?php

namespace Integrations;

use Zend\Router\Http\Segment;

return [
    'router' => [
        'routes' => [
            'getContractor' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/getContractors',
                    'defaults' => [
                        'controller' => Controller\OverdriveapiController::class,
                        'method' => 'POST',
                        'action' => 'getContractor',
                        'access' => [
                            
                        ],
                    ],
                ]
                ],
            'addContractor' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/addContractor1',
                    'defaults' => [
                        'controller' => Controller\OverdriveapiController::class,
                        'method' => 'PUT',
                        'action' => 'addContractor',
                        'access' => [
                            
                        ],
                    ],
                ]
                ],
                'addDriver' => [
                    'type' => Segment::class,
                    'options' => [
                    'route' => '/addDriver1',
                    'defaults' => [
                        'controller' => Controller\OverdriveapiController::class,
                        'method' => 'PUT',
                        'action' => 'addDriver',
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
