<?php

use Swoole\Coroutine;
use function Swoole\Coroutine\run;

run(function () {
    for ($i = 0; $i <= 10000; $i++) {
        Coroutine::create(function () {
            $socket = new Coroutine\Socket(AF_INET, SOCK_STREAM, 0);
            $retval = $socket->connect('127.0.0.1', 9527);
            while ($retval) {
                $data = 'hello';
                $head = pack('n', strlen($data));
                $n    = $socket->send($head . $data);
                var_dump($n);

                $data = $socket->recv();
                $head = substr($data, 0, 2);
                $len  = unpack('nlen', $head)['len'];
                var_dump(substr($data, 2));

                Coroutine::sleep(0.1);
            }
            var_dump($retval, $socket->errCode, $socket->errMsg);
        });
    }
});
