<?php

namespace MailRu\QueueProcessor\Worker;

use MailRu\QueueProcessor\Queue\QueuedQueue;

/**
 * В дочернем классе нужно определить в beforeRun() хендлер сигнала SIGTERM, который вызывет \MailRu\QueueProcessor\Worker\AbstractWorker::signalTerminate().
 *
 * @method QueuedQueue getQueue()
 */
abstract class QueuedWorker extends AbstractWorker
{
    /**
     * @param QueuedQueue $queue
     */
    public function setQueue(QueuedQueue $queue)
    {
        parent::setQueue($queue);
    }

    protected function beforeRun()
    {
        parent::beforeRun();
        $this->getQueue()->getProcessor()->getQueued()->close();
    }
}
