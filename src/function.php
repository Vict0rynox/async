<?php
/**
 * Created by PhpStorm.
 * User: victor
 * Date: 08.12.18
 * Time: 21:08
 */

namespace async;


use Exception;
use Generator;
use InvalidArgumentException;
use SplQueue;

function assert_resource($resource)
{
    if (false === is_resource($resource)) {
        throw new InvalidArgumentException(
            sprintf(
                'Argument must be a valid resource type. %s given.',
                gettype($resource)
            )
        );
    }
}

/**
 * @param $value
 * @return CoroutineReturnValue
 */
function retval($value): CoroutineReturnValue
{
    return new CoroutineReturnValue($value);
}

/**
 * @param Generator $generator
 * @return Generator
 * @throws Exception
 */
function stackedCoroutine(Generator $generator): Generator
{
    /** @var \SplStack<Generator> $stack */
    $stack = new \SplStack();
    $exception = null;

    while (true) {
        try {

            if ($exception) {
                $generator->throw($exception);
                $exception = null;
                continue;
            }

            $value = $generator->current();

            if ($value instanceof Generator) {
                $stack->push($generator);
                /** @var Generator $value */
                $generator = $value;
            } elseif ($value instanceof CoroutineReturnValue || !$generator->valid()) {
                if ($stack->isEmpty()) {
                    return;
                }
                /** @var Generator $generator */
                $generator = $stack->pop();
                $generator->send($value instanceof CoroutineReturnValue ? $value->getValue() : null);
            } else {
                try {
                    $generator->send(yield $generator->key() => $value);
                } catch (Exception $e) {
                    $generator->throw($e);
                }
            }
        } catch (Exception $e) {
            if ($stack->isEmpty()) {
                throw $e;
            }
            $generator = $stack->pop();
            $exception = $e;
        }

    }
}

function getTaskId()
{
    return new SystemCall(function (Task $task, Scheduler $scheduler) {
        $task->setSendValue($task->getTaskId());
        $scheduler->schedule($task);
    });
}

function newTask(Generator $coroutine)
{
    return new SystemCall(function (Task $task, Scheduler $scheduler) use ($coroutine) {
        $task->setSendValue($scheduler->newTask($coroutine));
        $scheduler->schedule($task);
    });
}

function killTask(Id $taskId)
{
    return new SystemCall(function (Task $task, Scheduler $scheduler) use ($taskId) {
        if ($scheduler->killTask($taskId)) {
            $scheduler->schedule($task);
        } else {
            throw new \InvalidArgumentException('Invalid task ID!');
        }
    });
}

function waitTask(Id $taskId)
{
    return new SystemCall(function (Task $task, Scheduler $scheduler) use ($taskId) {
        //Not pretty but work...
        @$scheduler->newTask((function (Task $waitTask, Task $task, Scheduler $scheduler) {
            while (true) {
                if ($waitTask->isFinished()) {
                    $scheduler->schedule($task);
                    break;
                }
                yield;
            }
        })($scheduler->getTask($taskId), $task, $scheduler));
    });
}

function execTask(Generator $coroutine)
{
    return new SystemCall(function (Task $task, Scheduler $scheduler) use ($coroutine) {
        $task->setSendValue($scheduler->newTask($coroutine));
        $scheduler->schedule($task);
    });
}

function waitForRead($socket)
{
    return new SystemCall(function (Task $task, Scheduler $scheduler) use ($socket) {
        $scheduler->waitForRead($socket, $task);
    });
}

function waitForWrite($socket)
{
    return new SystemCall(function (Task $task, Scheduler $scheduler) use ($socket) {
        $scheduler->waitForWrite($socket, $task);
    });
}
