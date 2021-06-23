<?php

namespace Ims;

use Zend\Router\Http\Segment;

return [
    'router' => [
        'routes' => [
            'insuredFunctionStructure' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/ims/insured/getFunctionStructure[/:operation]',
                    'defaults' => [
                        'controller' => Controller\InsuredController::class,
                        'method' => 'GET',
                        'action' => 'getFunctionStructure',
                    ],
                ],
            ],
            'producerFunctionStructure' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/ims/producer/getFunctionStructure[/:operation]',
                    'defaults' => [
                        'controller' => Controller\ProducerController::class,
                        'method' => 'GET',
                        'action' => 'getFunctionStructure',
                    ],
                ],
            ],
            'quoteFunctionStructure' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/ims/quote/getFunctionStructure[/:operation]',
                    'defaults' => [
                        'controller' => Controller\QuoteController::class,
                        'method' => 'GET',
                        'action' => 'getFunctionStructure',
                    ],
                ],
            ],
            'documentFunctionStructure' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/ims/document/getFunctionStructure[/:operation]',
                    'defaults' => [
                        'controller' => Controller\DocumentController::class,
                        'method' => 'GET',
                        'action' => 'getFunctionStructure',
                    ],
                ],
            ],
            'insured' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/ims/insured[/:operation]',
                    'defaults' => [
                        'controller' => Controller\InsuredController::class,
                    ],
                ],
            ],
            'producer' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/ims/producer[/:operation]',
                    'defaults' => [
                        'controller' => Controller\ProducerController::class,
                    ],
                ],
            ],
            'quote' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/ims/quote[/:operation]',
                    'defaults' => [
                        'controller' => Controller\QuoteController::class,
                    ],
                ],
            ],
            'document' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/ims/document[/:operation]',
                    'defaults' => [
                        'controller' => Controller\DocumentController::class,
                    ],
                ],
            ],

        ],
    ],
];
