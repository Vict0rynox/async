<?php
/**
 * Created by PhpStorm.
 * User: victor
 * Date: 08.12.18
 * Time: 21:10
 */

namespace async;


use Generator;

/**
 * Class CoSocket
 * @package async
 */
class CoSocket
{
    /**
     * @var resource
     */
    private $socket;

    /**
     * CoSocket constructor.
     * @param resource $socket
     */
    public function __construct($socket)
    {
        assert_resource($socket);
        $this->socket = $socket;
    }

    /**
     * @return Generator
     */
    public function accept(): Generator
    {
        yield waitForRead($this->socket);
        yield retval(new CoSocket(stream_socket_accept($this->socket, 0)));
    }

    /**
     * @param $size
     * @return Generator
     */
    public function read($size): Generator
    {
        yield waitForRead($this->socket);
        yield retval(fread($this->socket, $size));
    }

    /**
     * @param $string
     * @return Generator
     */
    public function write($string): ?Generator
    {
        yield waitForWrite($this->socket);
        fwrite($this->socket, $string);
    }

    /**
     *
     */
    public function close(): void
    {
        @fclose($this->socket);
    }
}