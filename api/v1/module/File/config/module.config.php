<?php

namespace File;

use Zend\Router\Http\Segment;

return [
    'router' => [
        'routes' => [
            'comment' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/file/:fileId/comment[/:id]',
                    'defaults' => [
                        'controller' => Controller\CommentController::class,
                        'access'=>[
                            // SET ACCESS CONTROL
                        ],
                    ],
                ],
            ],
            'commentchild' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/file/:fileId/comment/:id/getchildlist',
                    'defaults' => [
                        'controller' => Controller\CommentController::class,
                        'method' => 'GET',
                        'action' => 'getChildList',
                        'access' => [
                        ],
                    ],
                ],
            ],
            'snooze' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/file/:fileId/snooze',
                    'defaults' => [
                        'controller' => Controller\SnoozeController::class,
                        'method' => 'POST',
                        'action' => 'snoozeFile',
                        'access'=>[
                            // SET ACCESS CONTROL
                        ],
                    ],
                ],
            ],
            'subscriber' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/file/:fileId/subscriber[/:id]',
                    'defaults' => [
                        'controller' => Controller\SubscriberController::class,
                        'access'=>[
                            // SET ACCESS CONTROL
                        ],
                    ],
                ],
            ],
            'fileRygStatusUpdate' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/app/:appId/updateryg[/entity/:entityName]',
                    'defaults' => [
                        'controller' => Controller\FileCallbackController::class,
                        'action' => 'updateRygForFile',
                        'method' => 'POST',
                        'access' => [
                            // SET ACCESS CONTROL
                        ],
                    ],
                ],
            ],
            'fileCallbackUpdate' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/callback/file/update',
                    'defaults' => [
                        'controller' => Controller\FileCallbackController::class,
                        'method' => 'POST',
                        'action' => 'updateFile',
                        'access'=>[
                            // SET ACCESS CONTROL
                        ],
                    ],
                ],
            ],
            'esignFinalizedCallback' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/callback/esign/finalized',
                    'defaults' => [
                        'controller' => Controller\EsignCallbackController::class,
                        'action' => 'documentFinalized',
                        'method' => 'POST'
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
