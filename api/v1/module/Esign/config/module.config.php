<?php

namespace Esign;

use Zend\Router\Http\Segment;

return [
    'router' => [
        'routes' => [

            'esignStatus' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/esign/status/:docId',
                    'defaults' => [
                        'controller' => Controller\EsignController::class,
                        'action' => 'getStatus',
                        'method' => 'GET'
                    ],
                ],
            ],

            'esignCallback' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/esign/event',
                    'defaults' => [
                        'controller' => Controller\EsignCallbackController::class,
                        'action' => 'signEvent',
                        'method' => 'POST'
                    ],
                ],
            ]

        ],
    ],
];