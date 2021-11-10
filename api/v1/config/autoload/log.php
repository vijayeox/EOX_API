<?php

    return [ 
        'logger' => [
                'rootLogger' => [
                    'level' => 'DEBUG',
                    'appenders' => ['default']
                ],
                'loggers' => [
                    'ControllerLogger' => [
                        'level' => 'DEBUG',
                        'appenders' => ['default']
                    ],
                    'OxzionLogger' => [
                        'level' => 'DEBUG',
                        'appenders' => ['default']
                    ]
                ],
                'appenders' => [
                    'default' => [
                        'class' => 'Oxzion\Log4PHP\Appender\LoggerAppenderRollingFile',
                        'layout' => [
                            'class' => 'LoggerLayoutPattern',
                            'params' => [
                                'conversionPattern' => '%date{d/m/Y H:i:s,u} %C->%M %-5level [%pid] %msg%n%ex'
                            ]
                        ],
                        'params' => [
                            'file' => __DIR__."/../../logs/application-%s.log",
                            'append' => true,
                            'maxFileSize' => '250MB'
                         ]
                    // ],
                    // 'request' => [
                    //     'class' => 'LoggerAppenderDailyFile',
                    //     'layout' => [
                    //         'class' => 'LoggerLayoutPattern',
                    //         'params' => [
                    //             'conversionPattern' => '%date(d:m:Y H:i:s,u) [%pid] From:%server{REMOTE_ADDR}:%server{REMOTE_PORT} Request:[%request]%n%ex'
                    //         ]
                    //     ],
                    //     'params' => [
                    //         'file' => __DIR__."/../../logs/request.log",
                    //         'append' => true
                    //     ]
                    ]
                ]
            ]
        ];
?>
