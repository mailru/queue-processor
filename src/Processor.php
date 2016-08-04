<?php

namespace MailRu\QueueProcessor;

use Exception;
use MailRu\QueueProcessor\Config\ConfigReaderInterface;
use MailRu\QueueProcessor\Queue\AbstractQueue;
use MailRu\QueueProcessor\Util\ArrayUtils;
use MailRu\QueueProcessor\Worker\AbstractWorker;
use Mougrim\Logger\Logger;
use Mougrim\Logger\LoggerMDC;
use Mougrim\Logger\LoggerNDC;
use Mougrim\Pcntl\SignalHandler;
use RuntimeException;

class Processor
{
    protected $mainConfig;
    protected $terminate = false;

    private $signalHandler;
    private $maxWorkersQty;
    private $waitForWorkers = 30;
    private $workersQty;
    private $childrenInfo;
    private $freeWorkersNumbers;
    private $workersErrorsQty = [];
    /** @var ConfigReaderInterface */
    private $configReader;
    private $configReaderConfig;
    private $statusFilePath;
    private $pool;
    private $poolConfig;
    private $customPool;
    /** @var AbstractQueue[] */
    private $queues;

    /**
     * Очередь воркеров. Представляет из себя массив ников очередей, при этом количество элементов массива,
     * сооттветствующих каждому нику соответствует приоритету.
     *
     * @var array
     */
    private $workersQueue = [];
    private $workersQueueNumber = -1;
    private $queuesInfo;

    protected static $allowedAttributesInConfig = [
        'maxWorkersQty',
        'waitForWorkers',
    ];
    protected static $skipAttributesInConfig = [
        'servers',
    ];

    public function setCustomPool($customPool)
    {
        $this->customPool = $customPool;
    }

    public function setSignalHandler(SignalHandler $signalHandler)
    {
        $this->signalHandler = $signalHandler;
    }

    /**
     * @return SignalHandler
     *
     * @throws RuntimeException
     */
    public function getSignalHandler()
    {
        if ($this->signalHandler === null) {
            throw new RuntimeException("Property 'signalHandler' is require for processor");
        }

        return $this->signalHandler;
    }

    public function getMaxWorkersQty()
    {
        return $this->maxWorkersQty;
    }

    public function setMainConfig(array $config)
    {
        $this->mainConfig = $config;
    }

    public function setConfigReaderConfig(array $config_reader_config)
    {
        $this->configReaderConfig = $config_reader_config;
    }

    /**
     * @param string $statusFilePath
     */
    public function setStatusFilePath($statusFilePath)
    {
        $this->statusFilePath = (string) $statusFilePath;
    }

    public function run()
    {
        $this->reinit();

        $processorPid = posix_getpid();

        $this->childrenInfo = [];
        try {
            Logger::getLogger('queue')->info('Begin');
            $this->beforeRun();
            $this->workersQty = 0;
            $this->freeWorkersNumbers = range(0, $this->maxWorkersQty - 1);
            $lastReinitTime = time();

            $waitPidAttemptQty = 0;
            while (true) {
                $this->getSignalHandler()->dispatch();
                if ($lastReinitTime + 15 < time()) {
                    $this->reinit();
                    $lastReinitTime = time();
                }

                $this->changeStatusFile();

                if ($this->workersQty) {
                    $this->processSignalChild();
                }

                if ($this->terminate) {
                    $this->terminate(array_keys($this->childrenInfo));
                    break;
                }

                if ($this->workersQty >= $this->maxWorkersQty) {
                    // логируем не на каждый шаг в цикле
                    if ($waitPidAttemptQty % 100 === 0) {
                        Logger::getLogger('queue')->debug('workersQty is max, wait');
                    }
                    usleep(10000);
                    ++$waitPidAttemptQty;
                    continue;
                }
                $waitPidAttemptQty = 0;

                if ($this->queuesEmpty($this->queues)) {
                    Logger::getLogger('queue')->info('Tasks not found, sleep');
                    usleep(2000000);
                    continue;
                }

                $queueNick = $this->getNextQueueNick();
                if ($queueNick === null) {
                    usleep(10000);
                    continue;
                }
                Logger::getLogger('queue')->debug("queue nick for next worker: {$queueNick}");
                $queue = $this->queues[$queueNick];
                $tasksForWorker = $queue->sliceTasksForWorker();
                Logger::getLogger('queue')->trace('Count tasks for worker: '.count($tasksForWorker));
                $workerNumber = array_pop($this->freeWorkersNumbers);

                $pid = $this->fork();

                if ($pid) {
                    $this->childrenInfo[$pid] = [
                        'workerNumber' => $workerNumber,
                        'queueNick' => $queueNick,
                    ];
                    ++$this->workersQty;
                    ++$this->queuesInfo[$queueNick]['activeWorkersQty'];
                    Logger::getLogger('queue')->debug(
                        "worker running, workersQty={$this->workersQty},queue={$queueNick},activeWorkersQty={$this->queuesInfo[$queueNick]['activeWorkersQty']}"
                    );
                } else {
                    LoggerMDC::put('queueNick', $queueNick);
                    LoggerMDC::put('processorPid', $processorPid);
                    LoggerMDC::put('workerPid', posix_getpid());
                    LoggerNDC::push('worker');
                    $code = 0;

                    try {
                        $worker = $queue->getWorker();
                        $worker->setNumber($workerNumber);
                        $worker->run($tasksForWorker);
                    } catch (Exception $exception) {
                        Logger::getLogger('queue')->error('Exception in worker', $exception);
                        $code = 255;
                    }

                    LoggerNDC::pop();
                    $this->end($code);
                }
            }
        } catch (Exception $exception) {
            Logger::getLogger('queue')->error('Exception in processor', $exception);
            $this->terminate(array_keys($this->childrenInfo));
            throw $exception;
        }
    }

    private function terminate(array $pids)
    {
        Logger::getLogger('queue')->info('terminate');

        foreach ($this->queues as $queue) {
            $queue->unlockTasks();
        }

        foreach ($pids as $pid) {
            posix_kill($pid, SIGTERM);
        }

        $time = time();
        $killMode = false;
        while ($this->workersQty) {
            if (!$killMode && time() - $time > $this->waitForWorkers) {
                $killMode = true;
                Logger::getLogger('queue')->info("kill children ($this->workersQty)");
                foreach (array_keys($this->childrenInfo) as $pid) {
                    posix_kill($pid, SIGKILL);
                }
            }
            Logger::getLogger('queue')->info(
                'wait for '.($killMode ? 'kill ' : 'term ')."children ($this->workersQty)"
            );
            $this->processSignalChild();
            usleep(200000);
        }
        Logger::getLogger('queue')->info('processor finished');
    }

    /**
     * Использовать при SIGTERM.
     */
    public function signalTerminate()
    {
        // используется флаг, который проверяется только в процессоре и воркером игнорируется
        $this->terminate = true;
    }

    private function processSignalChild()
    {
        $childrenQty = 0;
        while (true) {
            $exitedWorkerPid = pcntl_waitpid(-1, $status, WNOHANG);

            if ($exitedWorkerPid === 0) {
                Logger::getLogger('queue')->debug("process SIGCHLD complete, childrenQty={$childrenQty}");

                return;
            }

            if ($exitedWorkerPid === -1) {
                Logger::getLogger('queue')->debug("Can't wait pid, error:'".var_export(error_get_last(), true)."'");

                return;
            }

            Logger::getLogger('queue')->debug("exitedWorkerPid={$exitedWorkerPid}");
            if (!isset($this->childrenInfo[$exitedWorkerPid])) {
                Logger::getLogger('queue')->error(
                    'pcntl_waitpid return unknown pid:'.var_export($exitedWorkerPid, true),
                    new Exception()
                );
                continue;
            }

            $isExited = false;
            $queueNick = $this->childrenInfo[$exitedWorkerPid]['queueNick'];

            if (pcntl_wifexited($status)) {
                $isExited = true;
                $code = pcntl_wexitstatus($status);
                Logger::getLogger('queue')->debug("exitCode={$code}");

                if (!isset($this->workersErrorsQty[$queueNick])) {
                    $this->workersErrorsQty[$queueNick] = 0;
                }

                if ($code) {
                    ++$this->workersErrorsQty[$queueNick];
                    Logger::getLogger('queue')->error(
                        "worker pid={$exitedWorkerPid} for queue {$queueNick} exit with code {$code}"
                    );
                } else {
                    $this->workersErrorsQty[$queueNick] = 0;
                }

                if ($this->workersErrorsQty[$queueNick] > $this->maxWorkersQty * 0.5) {
                    $message = "queue {$queueNick} worker errors qty = {$this->workersErrorsQty[$queueNick]}. Disable this queue.";
                    $this->queuesInfo[$queueNick]['state'] = 'error';
                    $this->queuesInfo[$queueNick]['message'] = $message;
                    Logger::getLogger('queue')->error($message);

                    if (isset($this->queues[$queueNick])) {
                        $this->queues[$queueNick]->unlockTasks();
                        unset($this->queues[$queueNick]);
                    }
                }
            }

            if (pcntl_wifsignaled($status)) {
                $isExited = true;
                $errorSignal = pcntl_wtermsig($status);
                Logger::getLogger('queue')->error("{$exitedWorkerPid} terminate by signal {$errorSignal}");
            }

            if (pcntl_wifstopped($status)) {
                $stopSignal = pcntl_wstopsig($status);
                Logger::getLogger('queue')->error("{$exitedWorkerPid} stop by signal {$stopSignal}");
            }

            if ($isExited) {
                --$this->workersQty;
                if (isset($this->queuesInfo[$queueNick])) {
                    --$this->queuesInfo[$queueNick]['activeWorkersQty'];
                    Logger::getLogger('queue')->debug(
                        "worker complete, workersQty={$this->workersQty},queue={$queueNick},activeWorkersQty={$this->queuesInfo[$queueNick]['activeWorkersQty']}"
                    );
                }
                $this->freeWorkersNumbers[] = $this->childrenInfo[$exitedWorkerPid]['workerNumber'];
                unset($this->childrenInfo[$exitedWorkerPid]);
            }

            ++$childrenQty;
        }
    }

    /**
     * @param AbstractQueue[] $queues
     *
     * @return bool true if tasks not exists, else false
     */
    private function queuesEmpty($queues)
    {
        $queuesEmpty = true;

        foreach ($queues as $queue) {
            $queue->populate($this->maxWorkersQty);
            if (!$queue->isEmpty()) {
                $queuesEmpty = false;
            }
        }

        return $queuesEmpty;
    }

    private function getNextQueueNick()
    {
        $tryNumber = 0;
        do {
            ++$this->workersQueueNumber;
            $this->workersQueueNumber = $this->workersQueueNumber % count($this->workersQueue);
            $queueNick = $this->workersQueue[$this->workersQueueNumber];

            if (!isset($this->queues[$queueNick]) ||
                $this->queuesInfo[$queueNick]['activeWorkersQty'] >= $this->queues[$queueNick]->getMaxWorkersQty() ||
                $this->queues[$queueNick]->isEmpty()
            ) {
                $queueNick = null;
            }

            // protection from looping
            if ($tryNumber > count($this->workersQueue)) {
                Logger::getLogger('queue')->debug('tasks not found or activeWorkersQty is max in all queues');
                break;
            }
            ++$tryNumber;
        } while ($queueNick === null);

        return $queueNick;
    }

    private function changeStatusFile()
    {
        if ($this->statusFilePath === null) {
            return;
        }

        $statusFileDir = dirname($this->statusFilePath);
        if (!file_exists($statusFileDir)) {
            mkdir($statusFileDir, 0775, true);
        }

        $status = $this->poolConfig;
        $date = new \DateTime();
        $status['time'] = $date->format('Y-m-d H:i:s');
        $status['terminate'] = $this->terminate;
        $status['activeWorkersQty'] = $this->workersQty;

        foreach ($status['queues'] as $queueNick => &$queueStatus) {
            $queueStatus = ArrayUtils::mergeArray($queueStatus, $this->queuesInfo[$queueNick]);
        }
        unset($queueStatus);
        Logger::getLogger('queue')->trace('Write status: '.var_export($status, true));

        $status = ArrayUtils::convertEncoding($status, 'utf-8');
        file_put_contents($this->statusFilePath, json_encode($status));
    }

    protected function reinit()
    {
        $previousPool = $this->pool;
        $previousConfigHash = null;

        if ($this->poolConfig !== null) {
            $previousConfigHash = ArrayUtils::hash($this->poolConfig);
        }

        $this->reinitConfig();

        if ($previousPool === $this->pool && $previousConfigHash === ArrayUtils::hash($this->poolConfig)) {
            Logger::getLogger('queue')->debug('Config not changed');

            // переинициализацию делать не надо
            return;
        }

        Logger::getLogger('queue')->info('Config changed, reinit');
        if ($this->queues) {
            Logger::getLogger('queue')->info('Unlock old tasks');
            foreach ($this->queues as $queue) {
                $queue->unlockTasks();
            }
        }

        $this->queues = [];
        $this->workersQueue = [];
        $previousQueuesInfo = $this->queuesInfo;
        $this->queuesInfo = [];
        foreach ($this->poolConfig as $attribute => $value) {
            if ($attribute === 'queues') {
                foreach ($value as $queueNick => $queueConfig) {
                    try {
                        if (isset($previousQueuesInfo[$queueNick])) {
                            $this->queuesInfo[$queueNick]['activeWorkersQty'] = $previousQueuesInfo[$queueNick]['activeWorkersQty'];
                        } else {
                            $this->queuesInfo[$queueNick]['activeWorkersQty'] = 0;
                        }

                        if (!isset($queueConfig['enabled']) || !$queueConfig['enabled']) {
                            Logger::getLogger('queue')->debug("{$queueNick} is disabled");
                            $this->queuesInfo[$queueNick]['state'] = 'disabled';
                            continue;
                        }
                        unset($queueConfig['enabled']);
                        $workerConfig = $queueConfig['worker'];
                        unset($queueConfig['worker']);
                        $queueClass = $queueConfig['class'];
                        unset($queueConfig['class']);
                        /** @var AbstractQueue $queue */
                        $queue = $this->create($queueClass, $queueConfig);
                        $queue->setProcessor($this);
                        $workerClass = $workerConfig['class'];
                        unset($workerConfig['class']);
                        /** @var AbstractWorker $worker */
                        $worker = $this->create($workerClass, $workerConfig);
                        $worker->setQueue($queue);
                        $queue->setWorker($worker);
                        $this->queues[$queueNick] = $queue;
                        $this->workersQueue = array_merge(
                            $this->workersQueue,
                            array_fill(0, $queue->getPriority(), $queueNick)
                        );
                        $this->queuesInfo[$queueNick]['state'] = 'active';
                    } catch (Exception $exception) {
                        Logger::getLogger('queue')->error('Can`t create queue', $exception);
                        $this->queuesInfo[$queueNick]['state'] = 'error';
                        $this->queuesInfo[$queueNick]['message'] = $exception->getMessage();
                    }
                }
            } elseif (in_array($attribute, self::$allowedAttributesInConfig, true)) {
                $this->{$attribute} = $value;
            } elseif (!in_array($attribute, self::$skipAttributesInConfig, true)) {
                Logger::getLogger('queue')->error("Unknown attribute {$attribute} in config");
            }
        }
        Logger::getLogger('queue')->info('Workers queue: '.var_export($this->workersQueue, true));
        Logger::getLogger('queue')->info("WaitForWorkers (sec): $this->waitForWorkers");
    }

    private function create($class, $config)
    {
        $object = new $class();
        foreach ($config as $attribute => $value) {
            $method = 'set'.ucfirst($attribute);
            if (!method_exists($object, $method)) {
                throw new RuntimeException("Unknown method {$method} for object ".get_class($object));
            }
            $object->$method($value);
        }

        return $object;
    }

    private function reinitConfig()
    {
        $this->pool = null;
        $this->poolConfig = null;

        $server = php_uname('n');
        $configFromFile = $this->getConfig();
        Logger::getLogger('queue')->trace('Config from file: '.var_export($configFromFile, true));
        foreach ($configFromFile as $pool => $poolConfig) {
            // todo оно вообще зачем? думаю нужно удалить
            // хотя возможно нужно для отладки
            // но тогда лучше сервер forceServer
            if (isset($this->customPool) && $this->customPool !== $pool) {
                continue;
            }

            if (in_array($server, $poolConfig['servers'], true)) {
                $this->pool = $pool;
                $this->poolConfig = $poolConfig;
                break;
            }
        }

        if ($this->poolConfig === null) {
            throw new RuntimeException("Queue processor pool not found for server {$server}");
        }

        Logger::getLogger('queue')->debug('pool: '.var_export($this->pool, true));

        foreach ($this->poolConfig['queues'] as $queueNick => $queueConfig) {
            if (!array_key_exists($queueNick, $this->mainConfig['queues'])) {
                Logger::getLogger('queue')->warn("unknown queue {$queueNick}");
                // очередь с неизвестным ником
                unset($this->poolConfig['queues'][$queueNick]);
                continue;
            }

            $mainQueueConfig = $this->mainConfig['queues'][$queueNick];

            if (!array_key_exists('class', $mainQueueConfig)) {
                throw new RuntimeException("Attribute 'class' is require for queue");
            }

            if (!array_key_exists('worker', $mainQueueConfig)) {
                throw new RuntimeException("Attribute 'worker' is require for queue");
            }

            if (!is_array($mainQueueConfig['worker']) || !array_key_exists('class', $mainQueueConfig['worker'])) {
                throw new RuntimeException('Worker class is require');
            }

            $this->poolConfig['queues'][$queueNick] = ArrayUtils::mergeArray($queueConfig, $mainQueueConfig);
        }

        Logger::getLogger('queue')->trace('Result pool config: '.var_export($this->poolConfig, true));
    }

    private function getConfig()
    {
        // create config reader if is't created
        if ($this->configReader === null) {
            if ($this->configReaderConfig === null) {
                throw new RuntimeException('Config reader config should not empty');
            }
            $configReaderConfig = $this->configReaderConfig;
            if (!isset($configReaderConfig['class'])) {
                throw new RuntimeException('You should specify config reader class');
            }
            $configReaderClass = $configReaderConfig['class'];
            unset($configReaderConfig['class']);
            $this->configReader = $this->create($configReaderClass, $configReaderConfig);
        }

        return $this->configReader->getConfig();
    }

    protected function beforeRun()
    {
        $this->getSignalHandler()->addHandler(SIGTERM, [$this, 'signalTerminate'], false);
        $this->getSignalHandler()->addHandler(SIGINT, [$this, 'signalTerminate'], false);
    }

    protected function end($status = 0)
    {
        exit($status);
    }

    private function fork()
    {
        for ($i = 0; $i < 3; ++$i) {
            if (isset($pid)) {
                usleep(1000000);
            }

            $pid = pcntl_fork();

            if ($pid >= 0) {
                return $pid;
            }
            $error = pcntl_get_last_error();
            Logger::getLogger('queue')->warn(
                "Can`t fork, retryNumber={$i}, pid: '".var_export($pid, true)."', error: '{$error}'",
                new Exception()
            );
        }

        throw new RuntimeException('Can`t fork');
    }
}
