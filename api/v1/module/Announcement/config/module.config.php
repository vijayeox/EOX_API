<?php

namespace Announcement;

use Zend\Router\Http\Segment;

return [
    'router' => [
        'routes' => [
            'announcement' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/[organization/:orgId/]announcement[/:announcementId]',
                    'defaults' => [
                        'controller' => Controller\AnnouncementController::class,
                        'access'=>[
                            // SET ACCESS CONTROL
                            'put'=> 'MANAGE_ANNOUNCEMENT_WRITE',
                            'post'=> 'MANAGE_ANNOUNCEMENT_WRITE',
                            'delete'=> 'MANAGE_ANNOUNCEMENT_WRITE',
                        ],
                    ],
                ],
            ],
            'announcementList' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/[organization/:orgId/]announcement/a',
                    'defaults' => [
                        'controller' => Controller\AnnouncementController::class,
                        'method' => 'GET',
                        'action' => 'announcementList'
                    ],
                ],
            ],
            'announcementToGroup' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/[organization/:orgId/]announcement/:announcementId/save',
                    'defaults' => [
                        'controller' => Controller\AnnouncementController::class,
                        'method' => 'POST',
                        'action' => 'announcementToGroup'
                    ],
                ],
            ],
            'announcementGroups' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/[organization/:orgId/]announcement/:announcementId/groups',
                    'defaults' => [
                        'controller' => Controller\AnnouncementController::class,
                        'method' => 'GET',
                        'action' => 'announcementGroups'
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
