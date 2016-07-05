<?php

namespace MailRu\QueueProcessor\Demo\config;

return [
    'testPool' => [
        'servers' => [
            'mougrim-1215N',
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
