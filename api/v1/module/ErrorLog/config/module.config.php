<?php

namespace ErrorLog;

use Zend\Router\Http\Segment;
use Zend\Router\Http\Method;

return [
    'router' => [
        'routes' => [
            'ox_error_retry' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/errorlog/:errorId/retry',
                    'method' => 'POST',
                    'defaults' => [
                        'controller' => Controller\RetryController::class,
                        'action' => 'retry',
                        'access' => [
                            // SET ACCESS CONTROL
                            'put' => '',
                            'post' => '',
                            'delete' => '',
                            'get' => '',
                            'retry' => '',
                        ],
                    ],
                ],
            ],
            'ox_error_log' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/errorlog[/:errorId]',
                    'defaults' => [
                        'controller' => Controller\ErrorController::class,
                        'access' => [
                            // SET ACCESS CONTROL
                            'put' => '',
                            'post' => '',
                            'delete' => '',
                            'get' => '',
                        ],
                    ],
                ],
            ],
            'logger' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/logger',
                    'defaults' => [
                        'controller' => Controller\LoggerController::class,
                        'access' => [
                            // SET ACCESS CONTROL
                            'post' => ''
                        ],
                    ],
                ],
            ],
        ],
    ],
    'view_manager' => [
        // We need to set this up so that we're allowed to return JSON
        // responses from our controller.
        'strategies' => ['ViewJsonStrategy',],
    ],
];
