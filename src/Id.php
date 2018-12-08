<?php
/**
 * Created by PhpStorm.
 * User: victor
 * Date: 08.12.18
 * Time: 21:05
 */

namespace async;

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
