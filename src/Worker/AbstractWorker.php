<?php

namespace MailRu\QueueProcessor\Worker;

use MailRu\QueueProcessor\Queue\AbstractQueue;
use MailRu\QueueProcessor\Task;
use Mougrim\Logger\Logger;

/*
 * В дочернем классе нужно определить в beforeRun() хендлер сигнала SIGTERM, который вызывет \MailRu\QueueProcessor\Worker\AbstractWorker::signalTerminate()
 */

abstract class AbstractWorker
{
    /**
     * @var AbstractQueue
     */
    private $queue;

    /**
     * @var int
     */
    private $number;

    /**
     * @var bool
     */
    protected $terminate = false;

    public function setQueue(AbstractQueue $queue)
    {
        $this->queue = $queue;
    }

    /**
     * @return AbstractQueue
     *
     * @throws \Exception
     */
    public function getQueue()
    {
        if ($this->queue === null) {
            throw new \Exception("Property 'queue' is require for queue");
        }

        return $this->queue;
    }

    /**
     * Проставляется процессором после форка.
     *
     * @param int $number Номер воркера
     */
    public function setNumber($number)
    {
        $this->number = (int) $number;
    }

    /**
     * Номер воркера не превышает количество воркеров - 1 и начинается от нуля.
     * Предлагаемое использование - возможность писать каждому воркеру в свой файл, что бы его не нужно было лочить.
     * При этом количество файлов будет строго ограниченным (не разрастаться по мере умирания чалдов и создание новых).
     *
     * @return int Номер воркера
     *
     * @throws \Exception
     */
    public function getNumber()
    {
        if (is_null($this->number)) {
            throw new \Exception('number is not set');
        }

        return $this->number;
    }

    /**
     * @param Task[] $tasks
     *
     * @throws \Exception
     */
    protected function doRun(array $tasks)
    {
        while ($tasks) {
            $this->getQueue()->getProcessor()->getSignalHandler()->dispatch();

            $task = array_shift($tasks);
            Logger::getLogger('queue')->debug("Process {$task->getId()}, class=".get_class($this));
            $this->process($task);

            if ($this->terminate) {
                Logger::getLogger('queue')->info('worker terminate');

                if ($tasks) {
                    $this->getQueue()->unlockTasks($tasks);
                }
                break;
            }
        }
    }

    /**
     * @param Task[] $tasks
     *
     * @throws \Exception
     */
    public function run(array $tasks)
    {
        Logger::getLogger('queue')->debug("Worker#{$this->getNumber()} begin");
        $this->beforeRun();

        $this->doRun($tasks);

        $this->afterRun();
        Logger::getLogger('queue')->debug("Worker#{$this->getNumber()} complete");
    }

    /**
     * Использовать при SIGTERM.
     */
    public function signalTerminate()
    {
        Logger::getLogger('queue')->info('worker is begin terminate');
        // используется флаг, который проверяется только в процессоре и ворцером игнорируется
        $this->terminate = true;
    }

    protected function beforeRun()
    {
        $signalHandler = $this->getQueue()->getProcessor()->getSignalHandler();
        // даже если сигнал был прислан до подписки, метод dispatch() еще не вызывался,
        // поэтому terminate нормально обработается
        $signalHandler->addHandler(SIGTERM, [$this, 'signalTerminate'], false);
        $signalHandler->addHandler(SIGINT, [$this, 'signalTerminate'], false);
        Logger::reopen();
    }

    protected function afterRun()
    {
    }

    abstract public function process(Task $task);
}
