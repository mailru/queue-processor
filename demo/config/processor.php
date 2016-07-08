<?php

namespace MailRu\QueueProcessor\Demo\config;

return [
    'testPool' => [
        'servers' => [
            php_uname('n'), // the network node hostname for this pool
        ],
        'maxWorkersQty' => 2,
        'queues' => [
            'testQueue' => [
                'enabled' => true,
                'priority' => 2,
                'tasksQtyPerWorker' => 10,
            ],
        ],
    ],
];
