<?php

namespace MailRu\QueueProcessor\Processor;

use MailRu\QueueProcessor\Queue\FileQueue;

/**
 * @author Mougrim <rinat@mougrim.ru>
 */
class FileProcessor extends AbstractProcessor
{
    protected function getQueueClass()
    {
        return FileQueue::class;
    }
}
