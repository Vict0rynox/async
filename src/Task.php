<?php
/**
 * Created by PhpStorm.
 * User: victor
 * Date: 08.12.18
 * Time: 21:05
 */

namespace async;

use Exception;
use Generator;

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
     * @var Exception
     */
    private $exception;

    /**
     * Task constructor.
     * @param $id
     * @param Generator $coroutine
     * @throws Exception
     */
    public function __construct(Id $id, Generator $coroutine)
    {
        $this->id = $id;
        $this->coroutine = stackedCoroutine($coroutine);
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

        if ($this->exception) {
            $returnValue = $this->coroutine->throw($this->exception);
            $this->exception = null;
            return $returnValue;
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

    /**
     * @param Exception $exception
     */
    public function setException(Exception $exception): void
    {
        $this->exception = $exception;
    }
}