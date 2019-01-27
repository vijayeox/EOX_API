<?php

namespace Project;

use Zend\Log\Logger;
use Zend\Router\Http\Segment;
use Zend\Log\Formatter\Simple;
use Zend\Log\Filter\Priority;
use Zend\Log\Processor\RequestId;

return [
    'router' => [
        'routes' => [
            'project' => [
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/project[/:projectId]',
                    'defaults' => [
                        'controller' => Controller\ProjectController::class,
                        'access'=>[
                            // SET ACCESS CONTROL
                            'put'=> 'MANAGE_PROJECT_WRITE',
                            'post'=> 'MANAGE_PROJECT_WRITE',
                            'delete'=> 'MANAGE_PROJECT_WRITE',
                            'get'=> 'MANAGE_PROJECT_READ',
                        ],
                    ],
                ],
            ],
            'projectuser' => [
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/project/:projectId/save',
                    'defaults' => [
                        'controller' => Controller\ProjectController::class,
                        'method' => 'POST',
                        'action' => 'saveUser',
                        'access' => [
                            'saveUser'=>'MANAGE_PROJECT_WRITE'
                        ],
                    ],
                ],
            ],
            'deleteproject' => [
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/project/:projectId/getusers',
                    'defaults' => [
                        'controller' => Controller\ProjectController::class,
                        'method' => 'GET',
                        'action' => 'getListOfUsers',
                        'access' => [
                            'getListOfUsers'=>'MANAGE_PROJECT_READ'
                        ],
                    ],
                ],
            ],
        ],
    ],
    'log' => [
        'ProjectLogger' => [
            'writers' => [
                'stream' => [
                    'name' => 'stream',
                    'priority' => \Zend\Log\Logger::ALERT,
                    'options' => [
                        'stream' => __DIR__ . '/../../../logs/Project.log',
                        'formatter' => [
                            'name' => \Zend\Log\Formatter\Simple::class,
                            'options' => [
                                'format' => '%timestamp% %priorityName% (%priority%): %message% %extra%','dateTimeFormat' => 'c',
                            ],
                        ],
                        'filters' => [
                            'priority' => \Zend\Log\Logger::INFO,],
                        ],
                    ],
                ],
                'processors' => [
                    'requestid' => [
                        'name' => \Zend\Log\Processor\RequestId::class,],
                    ],
                ],
            ],
            'view_manager' => [
                // We need to set this up so that we're allowed to return JSON
                // responses from our controller.
                'strategies' => ['ViewJsonStrategy',],
            ],
        ];