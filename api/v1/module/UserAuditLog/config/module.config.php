<?php

namespace UserAuditLog;

use Zend\Router\Http\Segment;

return [
    'router' => [
        'routes' => [
            'insert_activity' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/auditlog/activity/:activity',
                    'defaults' => [
                        'controller' => Controller\UserAuditLogController::class,
                        'method' => 'POST',
                        'action' => 'insertActivityTime',
                    ],
                ],
            ]
        ],
    ],
    'view_manager' => [
        // We need to set this up so that we're allowed to return JSON
        // responses from our controller.
        'strategies' => ['ViewJsonStrategy'],
    ],
];
