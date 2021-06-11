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
            'insured' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/ims/insured',
                    'defaults' => [
                        'controller' => Controller\InsuredController::class,
                    ],
                ],
            ],
            'producer' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/ims/producer',
                    'defaults' => [
                        'controller' => Controller\ProducerController::class,
                    ],
                ],
            ],
            'quote' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/ims/quote',
                    'defaults' => [
                        'controller' => Controller\QuoteController::class,
                    ],
                ],
            ],
            'createInsured' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/ims/createInsured[/:operation]',
                    'defaults' => [
                        'controller' => Controller\InsuredController::class,
                        'action' => 'createInsured',
                        'method' => 'POST',
                    ],
                ],
            ],
            'createProducer' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/ims/createProducer[/:operation]',
                    'defaults' => [
                        'controller' => Controller\ProducerController::class,
                        'action' => 'createProducer',
                        'method' => 'POST',
                    ],
                ],
            ],
            'createQuote' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/ims/createQuote[/:operation]',
                    'defaults' => [
                        'controller' => Controller\QuoteController::class,
                        'action' => 'createQuote',
                        'method' => 'POST',
                    ],
                ],
            ],
            'createDocument' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/ims/createDocument[/:operation]',
                    'defaults' => [
                        'controller' => Controller\QuoteController::class,
                        'action' => 'createDocument',
                        'method' => 'POST',
                    ],
                ],
            ],
            'getInsured' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/ims/getInsured[/:operation]',
                    'defaults' => [
                        'controller' => Controller\InsuredController::class,
                        'action' => 'getInsured',
                        'method' => 'POST',
                    ],
                ],
            ],
            'getProducer' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/ims/getProducer[/:operation]',
                    'defaults' => [
                        'controller' => Controller\ProducerController::class,
                        'action' => 'getProducer',
                        'method' => 'POST',
                    ],
                ],
            ],
            'getQuote' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/ims/getQuote[/:operation]',
                    'defaults' => [
                        'controller' => Controller\QuoteController::class,
                        'action' => 'getQuote',
                        'method' => 'POST',
                    ],
                ],
            ],
            'getDocument' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/ims/getDocument[/:operation]',
                    'defaults' => [
                        'controller' => Controller\QuoteController::class,
                        'action' => 'getDocument',
                        'method' => 'POST',
                    ],
                ],
            ],

        ],
    ],
];
