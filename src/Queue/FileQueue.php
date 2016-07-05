<?php

namespace MailRu\QueueProcessor\Queue;

use MailRu\QueueProcessor\Task;
use Mougrim\Logger\Logger;

/**
 * @author Mougrim <rinat@mougrim.ru>
 *
 * Класс сделан чисто как пример реализации, не является гибким и надежным.
 * Если воркер с задачами умрет, то задачи не будут обработаны.
 */
class FileQueue extends AbstractQueue
{
    private $tasksFile;
    private $statusFile;

    /**
     * @return string
     *
     * @throws \RuntimeException
     */
    public function getTasksFile()
    {
        if (!$this->tasksFile) {
            throw new \RuntimeException('Tasks file might not empty');
        }

        return $this->tasksFile;
    }

    /**
     * @param string $tasksFile
     */
    public function setTasksFile($tasksFile)
    {
        $this->tasksFile = (string) $tasksFile;
    }

    /**
     * @return string
     *
     * @throws \RuntimeException
     */
    public function getStatusFile()
    {
        if (!$this->statusFile) {
            throw new \RuntimeException('Status file might not empty');
        }

        return $this->statusFile;
    }

    /**
     * @param string $statusFile
     */
    public function setStatusFile($statusFile)
    {
        $this->statusFile = (string) $statusFile;
    }

    /**
     * @return \SplFileObject
     *
     * @throws \RuntimeException
     */
    protected function getStatusFileObject()
    {
        if (!is_file($this->getStatusFile())) {
            touch($this->getStatusFile());
        }

        return new \SplFileObject($this->getStatusFile(), 'r+');
    }

    /**
     * @param int $limit
     *
     * @return Task[]
     *
     * @throws \RuntimeException
     */
    protected function fetchTasks($limit)
    {
        $logger = Logger::getLogger('file-queue');
        $statusFileObject = $this->getStatusFileObject();
        if (!$statusFileObject->flock(LOCK_EX)) {
            throw new \RuntimeException("Can't get lock for status file {$this->getStatusFile()}");
        }

        $seek = $statusFileObject->fgets();
        if ($seek === false) {
            throw new \RuntimeException("Can't get seek from status file {$this->getStatusFile()}");
        }
        if (!$seek) {
            $seek = 0;
        }
        $seek = (integer) $seek;
        $tasksFileObject = new \SplFileObject($this->getTasksFile(), 'r');
        if ($tasksFileObject->fseek($seek) !== 0) {
            throw new \RuntimeException("Can't seek to {$seek} in tasks file {$this->getTasksFile()}");
        }
        $logger->debug("Current seek {$seek}");
        $tasks = [];
        $taskNumber = 0;
        while ($taskNumber < $limit && !$tasksFileObject->eof()) {
            $this->getProcessor()->getSignalHandler()->dispatch();
            $row = $tasksFileObject->fgetcsv();
            if ($row === false) {
                throw new \RuntimeException("Can't get row from file {$this->getTasksFile()}");
            }
            // пропускаем пустые строки
            if (!$row || $row === [null]) {
                continue;
            }
            $seek = $tasksFileObject->ftell();
            if ($seek === false) {
                throw new \RuntimeException("Can't get seek from file {$this->getTasksFile()}");
            }
            if (count($row) < 2) {
                $eventId = $row[0];
                $data = null;
            } else {
                list($eventId, $data) = $row;
            }
            $tasks[] = new Task($eventId, $data);
            ++$taskNumber;
        }
        if ($statusFileObject->fseek(0) !== 0) {
            throw new \RuntimeException("Can't seek to 0 in status file {$this->getStatusFile()}");
        }
        if (!$statusFileObject->ftruncate(0)) {
            throw new \RuntimeException("Can't truncate status file {$this->getStatusFile()}");
        }
        if (!$statusFileObject->fwrite($seek)) {
            throw new \RuntimeException("Can't write new seek {$seek} to status file {$this->getStatusFile()}");
        }
        $logger->debug("New seek {$seek}");

        if (!$statusFileObject->flock(LOCK_UN)) {
            $logger->warn("Can't release lock");
        }

        return $tasks;
    }

    public function unlockTasks(array $tasks = null)
    {
        $logger = Logger::getLogger('file-queue');
        $logger->info('unlock tasks');
        if ($tasks === null) {
            $tasks = $this->tasks;
        }
        $statusFileObject = $this->getStatusFileObject();
        if (!$statusFileObject->flock(LOCK_EX)) {
            throw new \RuntimeException("Can't get lock for status file {$this->getStatusFile()}");
        }

        $tasksFileObject = new \SplFileObject($this->getTasksFile(), 'a');
        foreach ($tasks as $task) {
            if ($tasksFileObject->fputcsv([$task->getId(), $task->getData()]) === false) {
                throw new \RuntimeException("Can't write task to tasks file {$this->getTasksFile()}");
            }
        }

        if (!$statusFileObject->flock(LOCK_UN)) {
            $logger->warn("Can't release lock");
        }
    }
}
