<?php
/**
 * Created by PhpStorm.
 * User: victor
 * Date: 08.12.18
 * Time: 21:06
 */

namespace async;

/**
 * Class CoroutineReturnValue
 * @package async
 */
class CoroutineReturnValue
{
    private $value;

    /**
     * CoroutineReturnValue constructor.
     * @param $value
     */
    public function __construct($value)
    {
        $this->value = $value;
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }
}
