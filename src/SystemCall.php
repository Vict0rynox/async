<?php
/**
 * Created by PhpStorm.
 * User: victor
 * Date: 08.12.18
 * Time: 21:06
 */

namespace async;

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