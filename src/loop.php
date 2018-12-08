<?php /** @noinspection PhpUndefinedClassInspection */

/**
 * Created by PhpStorm.
 * User: victor
 * Date: 07.12.18
 * Time: 21:16
 */

namespace async;
use Exception;
use Generator;


require_once '../vendor/autoload.php';

#####################################

function childTask()
{
    $id = yield getTaskId();
    while (true) {
        echo "Child task $id still alive!\n";
        yield;
    }
}

function testKillTask()
{
    $id = yield getTaskId();
    try {
        yield killTask(new Id('testKill'));
    } catch (Exception $exception) {
        echo "Task $id Tried to kill task 500 but failed: ", $exception->getMessage(), "\n";
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

/**
 * @param $port
 * @return Generator
 * @throws Exception
 */
function server($port)
{
    function handleClient(CoSocket $socket)
    {

        $data = yield $socket->read(8192);

        $msg = "Received following request:\n\n$data";
        $msgLength = strlen($msg);

        $response = <<<RES
HTTP/1.1 200 OK\r
Content-Type: text/plain\r
Content-Length: $msgLength\r
Connection: close\r
\r
$msg
RES;
        yield $socket->write($response);
        yield $socket->close();
    }

    echo "Starting server at port $port...\n";

    $socket = @stream_socket_server("tcp://localhost:$port", $errNo, $errStr);
    if (!$socket) {
        throw new RuntimeException($errStr, $errNo);
    }
    stream_set_blocking($socket, 0);

    $socket = new CoSocket($socket);
    while (true) {
        yield newTask(handleClient(yield $socket->accept()));
    }
}

//@$scheduler->newTask(testTask(3));
$scheduler = new Scheduler();
$scheduler->newTask(testKillTask());
$scheduler->run();