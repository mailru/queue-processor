<?php

namespace MailRu\QueueProcessor\Demo\config;

use MailRu\QueueProcessor\Config\AutoConfigReader;
use MailRu\QueueProcessor\Demo\Worker\TestWorker;

/**
 * @var \Composer\Autoload\ClassLoader $loader
 */
$loader->setPsr4('MailRu\\QueueProcessor\\Demo\\', 'demo/src');

return [
    'loggerConfig' => require __DIR__.'/logger.php',
    'processorConfig' => [
        'configReader' => [
            'class' => AutoConfigReader::class,
            'configPath' => __DIR__.'/processor.php',
        ],
        'statusFilePath' => __DIR__.'/../shared/status',
    ],
    'mainConfig' => [
        'queues' => [
            'testQueue' => [
                'info' => 'test worker',
                'statusFile' => __DIR__.'/../shared/queue/testQueue/status',
                'tasksFile' => __DIR__.'/../shared/queue/testQueue/tasks',
                'worker' => [
                    'class' => TestWorker::class,
                    'testField' => 'testValue',
                ],
            ],
        ],
    ],
];
