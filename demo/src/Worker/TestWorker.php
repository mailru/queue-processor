<?php

namespace MailRu\QueueProcessor\Demo\Worker;

use MailRu\QueueProcessor\Task;
use MailRu\QueueProcessor\Worker\AbstractWorker;
use Mougrim\Logger\Logger;

/**
 * @author Mougrim <rinat@mougrim.ru>
 */
class TestWorker extends AbstractWorker
{
    protected $testField;

    public function setTestField($value)
    {
        $this->testField = $value;
    }

    public function process(Task $task)
    {
        Logger::getLogger('queue')->info("begin id:{$task->getId()} (data:{$this->testField})");
        usleep(mt_rand(200000, 500000));
        // todo remove from queue, or unlock
        Logger::getLogger('queue')->info("end id:{$task->getId()} (data:{$this->testField})");
    }
}
