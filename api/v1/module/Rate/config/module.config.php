<?php

namespace Rate;

use Zend\Router\Http\Segment;

return [
    'router' => [
        'routes' => [
            'rate' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/rate[/:rateUuid]',
                    'defaults' => [
                        'controller' => Controller\RateController::class,
                        'access' => [
                            // SET ACCESS CONTROL
                            'put' => 'MANAGE_RATES_WRITE',
                            'post' => 'MANAGE_RATES_WRITE',
                            'delete' => 'MANAGE_RATES_WRITE',
                            'get' => 'MANAGE_RATES_READ',
                        ],
                    ],
                ],
            ],
            'rateCondition' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/ratecondition[/:conditionUuid]',
                    'defaults' => [
                        'controller' => Controller\RateConditionController::class,
                        'access' => [
                            // SET ACCESS CONTROL
                            'put' => 'MANAGE_RATES_WRITE',
                            'post' => 'MANAGE_RATES_WRITE',
                            'delete' => 'MANAGE_RATES_WRITE',
                            'get' => 'MANAGE_RATES_READ',
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
