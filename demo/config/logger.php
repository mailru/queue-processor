<?php

namespace MailRu\QueueProcessor\Demo\config;

use Mougrim\Logger\Appender\AppenderStream;
use Mougrim\Logger\Layout\LayoutPattern;
use Mougrim\Logger\Logger;

return [
    'policy' => [
        'ioError' => 'trigger_warn',
        'configurationError' => 'trigger_warn',
    ],
    'renderer' => [
        'nullMessage' => '-',
    ],
    'layouts' => [
        'console' => [
            'class' => LayoutPattern::class,
            'pattern' => '{pid} [{date:Y-m-d H:i:s}] {global:_SERVER.USER} {logger}.{level} [{mdc}][{ndc}] {message} {ex}',
        ],
    ],
    'appenders' => [
        'console_log' => [
            'class' => AppenderStream::class,
            'layout' => 'console',
            'stream' => __DIR__.'/../shared/logs/queue-processor.log',
        ],
    ],
    'root' => [
        'appenders' => ['console_log'],
        'minLevel' => Logger::DEBUG,
    ],
];
