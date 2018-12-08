<?php
/**
 * Created by PhpStorm.
 * User: victor
 * Date: 08.12.18
 * Time: 21:06
 */

namespace async;

use Generator;
use SplQueue;

/**
 * Class Scheduler
 * @package async
 */
class Scheduler
{

    /**
     * @var array
     */
    private $taskMap = [];

    /**
     * @var SplQueue<Task>
     */
    private $taskQueue;

    /**
     * @var array
     */
    private $waitingForRead = [];

    /**
     * @var array
     */
    private $waitingForWrite = [];

    /**
     * @var Id
     */
    private $ioPollTaskId;


    /**
     * Scheduler constructor.
     */
    public function __construct()
    {
        $this->taskQueue = new SplQueue();
    }

    /**
     * @param Generator $coroutine
     * @return Id
     */
    public function newTask(Generator $coroutine): Id
    {
        $taskId = Id::generateId(8);
        $task = new Task($taskId, $coroutine);
        $this->taskMap[(string)$taskId] = $task;
        $this->schedule($task);
        return $taskId;
    }

    /**
     * @param Id $taskId
     * @return bool
     */
    public function killTask(Id $taskId): bool
    {
        if (!isset($this->taskMap[(string)$taskId])) {
            return false;
        }

        unset($this->taskMap[(string)$taskId]);
        /**
         * @var  $id
         * @var Task $task
         */
        foreach ($this->taskQueue as $id => $task) {
            if ($task->getTaskId() === $taskId) {
                unset($this->taskQueue[$id]);
                break;
            }
        }
        return true;
    }

    /**
     * @param Id $taskId
     * @return Task
     */
    public function getTask(Id $taskId): Task
    {
        return $this->taskMap[(string)$taskId];
    }

    /**
     * @param Task $task
     */
    public function schedule(Task $task): void
    {
        $this->taskQueue->enqueue($task);
    }

    /**
     * @param $socket
     * @param Task $task
     */
    public function waitForRead($socket, Task $task): void
    {
        assert_resource($socket);
        if (isset($this->waitingForRead[(int)$socket])) {
            $this->waitingForRead[(int)$socket]['task'][] = $task;
        } else {
            $this->waitingForRead[(int)$socket] = [
                'socket' => $socket,
                'task' => [$task]
            ];
        }
    }

    /**
     * @param $socket
     * @param Task $task
     */
    public function waitForWrite($socket, Task $task): void
    {
        assert_resource($socket);
        if (isset($this->waitingForWrite[(int)$socket])) {
            $this->waitingForWrite[(int)$socket]['task'][] = $task;
        } else {
            $this->waitingForWrite[(int)$socket] = [
                'socket' => $socket,
                'task' => [$task]
            ];
        }
    }

    /**
     *
     */
    public function run(): void
    {
        $this->ioPollTaskId = $this->newTask($this->ioPollTask());
        while (!$this->taskQueue->isEmpty()) {
            /** @var Task $task */
            $task = $this->taskQueue->dequeue();
            $returnValue = $task->run();

            if ($returnValue instanceof SystemCall) {
                try {
                    $returnValue($task, $this);
                } catch (\Exception $exception) {
                    $task->setException($exception);
                    $this->schedule($task);
                }
                continue;
            }

            if ($task->isFinished()) {
                unset($this->taskMap[(string)$task->getTaskId()]);
            } else {
                $this->schedule($task);
            }
        }
    }

    /**
     * @return Generator
     */
    private function ioPollTask(): Generator
    {
        while (!$this->isIoPollTaskOnly()) {
            if ($this->taskQueue->isEmpty()) {
                $this->ioPull(null);
            } else {
                $this->ioPull(0);
            }
            yield;
        }
    }

    /**
     * @param $timeout
     */
    private function ioPull($timeout): void
    {
        $readSockets = array_column($this->waitingForRead, 'socket');
        $writeSockets = array_column($this->waitingForWrite, 'socket');

        $expectSockets = null;
        if (!@stream_select($readSockets, $writeSockets, $expectSockets, $timeout)) {
            return;
        }

        foreach ($readSockets as $readSocket) {
            /** @var Task[] $tasks */
            ['task' => $tasks] = $this->waitingForRead[(int)$readSocket];
            unset($this->waitingForRead[(int)$readSocket]);

            foreach ($tasks as $task) {
                $this->schedule($task);
            }
        }

        foreach ($writeSockets as $writeSocket) {
            /** @var Task[] $tasks */
            ['task' => $tasks] = $this->waitingForWrite[(int)$writeSocket];
            unset($this->waitingForWrite[(int)$writeSocket]);

            foreach ($tasks as $task) {
                $this->schedule($task);
            }
        }

    }

    private function isIoPollTaskOnly(): bool
    {
        return $this->taskQueue->isEmpty() &&
            count($this->taskMap) === 1 &&
            isset($this->taskMap[(string)$this->ioPollTaskId]);
    }
}
