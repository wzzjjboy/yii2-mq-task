<?php
return [
    'components' => [
        'db' => [
            'class' => 'yii\db\Connection',
            'dsn' => 'mysql:host=10.21.32.3;port=3306;dbname=providers',
            'username' => 'gordon',
            'password' => '4qYAEZ6scVNYPLTWRviT',
            'charset' => 'utf8',
            'tablePrefix' => 'gpi_',
            'commandClass' => 'yii2\mq_task\components\DbCommand',
        ],
        'redis' => [
            'class' => 'yii2\mq_task\components\RedisConnection',
            'hostname' => '10.21.32.3',
            'port' => 6379,
            'database' => 1, //服务商
            'password' => '63KxsHOY4g939Apq'
        ],
        'invoiceRedisEvent' => [
            'class'         => 'console\mqTask\InvoiceRedisEvent',
            'host'          => '10.21.32.3',
            'port'          => '5672',
            'username'      => 'rabbit',
            'password'      => 'aTjHMj7opZ3d5Kw6',
            'exchange_name' => 'invoice.event2',
            'queue_name'    => 'invoice.event2#from.redis2',
            'routing_key'   => 'from.redis2',
        ],
        'messageQueue'              => [
            'class'     => 'yii2\mq_task\basic\MQEngine',
            'host'      => '127.0.0.1',
            'port'      => '9502',
            'daemonize' => false,
            'log'       => [
                'class'    => 'yii2\mq_task\basic\Log',
                'category' => 'mq_task',
            ],
            'tasks'     => [
                'invoiceRedisEvent'          => 5, //同布开票中心非商户平台开的发票
            ]
        ]
    ]
];