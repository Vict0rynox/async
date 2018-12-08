<?php /** @noinspection PhpUndefinedClassInspection */

/**
 * Created by PhpStorm.
 * User: victor
 * Date: 07.12.18
 * Time: 21:16
 */

namespace async;

use Generator;
use SplQueue;

class GenerateIdException extends \RuntimeException
{

}

final class Id
{

	private $id;

	/**
	 * Id constructor.
	 * @param $id
	 */
	public function __construct($id)
	{
		$this->id = $id;
	}

	/**
	 * @return mixed
	 */
	public function value()
	{
		return $this->id;
	}

	/**
	 * @return string
	 */
	public function __toString()
	{
		return (string)$this->id;
	}

	/**
	 * @param $len
	 * @param string $chars
	 * @return Id
	 */
	public static function generateId($len, $chars = 'qwertasdfgzxcvbyuiophjklnm123456789'): Id
	{
		$charList = str_split($chars);
		if (count($charList) === 0) {
			throw new \InvalidArgumentException('Chars must bee non empty string.');
		}
		try {
			$id = '';
			for ($i = 0; $i < $len; $i++) {
				$charIndex = random_int(0, count($charList) - 1);
				$id .= $charList[$charIndex];
			}
			return new Id($id);
		} catch (\Throwable $exception) {
			throw new GenerateIdException('Can\'t generate id.', $exception->getCode(), $exception);
		}
	}
}

/**
 * Class Task
 * @package async
 */
class Task
{
	/**
	 * @var Id
	 */
	private $id;

	/**
	 * @var Generator
	 */
	private $coroutine;

	/**
	 * @var mixed
	 */
	private $sendValue;

	/**
	 * @var bool
	 */
	private $beforeFirstYield = true;

	/**
	 * Task constructor.
	 * @param $id
	 * @param Generator $coroutine
	 */
	public function __construct(Id $id, Generator $coroutine)
	{
		$this->id = $id;
		$this->coroutine = $coroutine;
	}

	/**
	 * @return Id
	 */
	public function getTaskId(): Id
	{
		return $this->id;
	}

	/**
	 * @param $sendValue
	 */
	public function setSendValue($sendValue): void
	{
		$this->sendValue = $sendValue;
	}

	/**
	 * @return mixed
	 */
	public function run()
	{
		if ($this->beforeFirstYield) {
			$this->beforeFirstYield = false;
			return $this->coroutine->current();
		}
		$returnValue = $this->coroutine->send($this->sendValue);
		$this->sendValue = null;
		return $returnValue;
	}

	/**
	 * @return bool
	 */
	public function isFinished(): bool
	{
		return !$this->coroutine->valid();
	}
}

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
	public function waitForRead(resource $socket, Task $task): void
	{
		if (!isset($this->waitingForRead[(int)$socket])) {
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
	public function waitForWrite(resource $socket, Task $task): void
	{
		if (!isset($this->waitingForWrite[(int)$socket])) {
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
		while (!$this->taskQueue->isEmpty()) {
			/** @var Task $task */
			$task = $this->taskQueue->dequeue();
			$returnValue = $task->run();

			if ($returnValue instanceof SystemCall) {
				$returnValue($task, $this);
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
	 * @param $timeout
	 */
	private function ioPull($timeout)
	{
		$readSockets = array_column($this->waitingForRead, 'socket');
		$writeSockets = array_column($this->waitingForWrite, 'socket');

		$expectSockets = [];
		if (!stream_select($readSockets, $writeSockets, $expectSockets, $timeout)) {
			return;
		}
		
	}
}


/**
 * Class SystemCall
 * @package async
 */
class SystemCall
{

	/**
	 * @var callable
	 */
	private $callback;

	/**
	 * SystemCall constructor.
	 * @param callable $callback
	 */
	public function __construct(callable $callback)
	{
		$this->callback = $callback;
	}

	/**
	 * @param Task $task
	 * @param Scheduler $scheduler
	 * @return mixed
	 */
	public function __invoke(Task $task, Scheduler $scheduler)
	{
		$callback = $this->callback;
		return $callback($task, $scheduler);
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
		$task->setSendValue($scheduler->killTask($taskId));
		$scheduler->schedule($task);
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

#####################################

function childTask()
{
	$id = yield getTaskId();
	while (true) {
		echo "Child task $id still alive!\n";
		yield;
	}
}

function testTask($max)
{
	for ($i = 1; $i <= $max; ++$i) {
		$id = yield getTaskId();
		echo "This is task $id iteration $i.\n";
		if ($i === 4) {
			yield waitTask(yield execTask(testTask(3)));
		}
		yield;
	}
}

$scheduler = new Scheduler();

/**
 * @return Generator
 */
function writer()
{
	while (true) {
		echo yield;
	}
}

//@$scheduler->newTask(testTask(3));
@$scheduler->newTask(testTask(10));

$scheduler->run();