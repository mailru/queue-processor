<?php

namespace MailRu\QueueProcessor\Queue;

use MailRu\QueueProcessor\Processor;
use MailRu\QueueProcessor\Task;
use MailRu\QueueProcessor\Worker\AbstractWorker;
use RuntimeException;

abstract class AbstractQueue
{
    /** @var Task[] */
    protected $tasks = [];

    /**
     * @var string
     */
    private $info;
    private $priority;
    private $tasksQtyPerWorker;
    private $processor;
    private $worker;
    private $maxWorkersQty;

    /**
     * @return string
     */
    public function getInfo()
    {
        return $this->info;
    }

    /**
     * @param string $info
     */
    public function setInfo($info)
    {
        $this->info = (string) $info;
    }

    /**
     * @param int $maxWorkersQty
     */
    public function setMaxWorkersQty($maxWorkersQty)
    {
        $this->maxWorkersQty = (integer) $maxWorkersQty;
    }

    /**
     * @return int
     */
    public function getMaxWorkersQty()
    {
        if ($this->maxWorkersQty !== null) {
            return $this->maxWorkersQty;
        } else {
            return $this->getProcessor()->getMaxWorkersQty();
        }
    }

    /**
     * @param int $priority
     */
    public function setPriority($priority)
    {
        $this->priority = (integer) $priority;
    }

    /**
     * @return int
     *
     * @throws RuntimeException
     */
    public function getPriority()
    {
        if ($this->priority === null) {
            throw new RuntimeException("Property 'priority' is require for queue");
        }

        return $this->priority;
    }

    /**
     * @param int $tasksQtyPerWorker
     */
    public function setTasksQtyPerWorker($tasksQtyPerWorker)
    {
        $this->tasksQtyPerWorker = (integer) $tasksQtyPerWorker;
    }

    /**
     * @return int
     *
     * @throws RuntimeException
     */
    public function getTasksQtyPerWorker()
    {
        if ($this->tasksQtyPerWorker === null) {
            throw new RuntimeException("Property 'tasksQtyPerWorker' is require for queue");
        }

        return $this->tasksQtyPerWorker;
    }

    public function setProcessor(Processor $processor)
    {
        $this->processor = $processor;
    }

    /**
     * @return Processor
     *
     * @throws RuntimeException
     */
    public function getProcessor()
    {
        if ($this->processor === null) {
            throw new RuntimeException("Property 'processor' is require for queue");
        }

        return $this->processor;
    }

    public function setWorker(AbstractWorker $worker)
    {
        $this->worker = $worker;
    }

    /**
     * @return AbstractWorker
     *
     * @throws RuntimeException
     */
    public function getWorker()
    {
        if ($this->worker === null) {
            throw new RuntimeException("Property 'worker' is require for queue");
        }

        return $this->worker;
    }

    public function populate($maxWorkersQty)
    {
        if (count($this->tasks) < $maxWorkersQty * $this->getTasksQtyPerWorker()) {
            $limit = $maxWorkersQty * $this->getTasksQtyPerWorker() * 2 - count($this->tasks);
            $this->tasks = array_merge($this->tasks, $this->fetchTasks($limit));
        }
    }

    public function isEmpty()
    {
        return empty($this->tasks);
    }

    /**
     * @return Task[]
     */
    public function sliceTasksForWorker()
    {
        return array_splice($this->tasks, 0, $this->getTasksQtyPerWorker());
    }

    /**
     * @param int $limit
     *
     * @return Task[]
     */
    abstract protected function fetchTasks($limit);

    /**
     * @param Task[]|null $tasks
     */
    abstract public function unlockTasks(array $tasks = null);
}
