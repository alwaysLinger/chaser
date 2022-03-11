<?php

namespace Al\Chaser\Commands;

use Al\Chaser\Connections\TcpConnection;
use Al\Chaser\Events\Timer;
use Al\Chaser\Servers\Server;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class StartCommand extends Command
{
    protected function configure()
    {
        $this->setName('start')
            ->setDescription('start register servers')
            ->setHelp("This command allows you to start servers");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $server = new Server($this->getApplication()->app, 'tcp');
        $server->on('connect', function (Server $server, TcpConnection $connection) use ($output) {
            // $aid = Timer::after(1, function ($timerId, $args) {
            //     var_dump($timerId, $args);
            // }, ['after678678']);
            // $id  = Timer::tick(0.5, function ($timerId, $args) {
            //     var_dump($timerId, $args);
            // }, ['tick55']);
            // $id = Timer::tick(1, function ($timerId, $args) {
            //     var_dump($timerId, $args);
            // }, ['tick1']);
            // $aid = Timer::after(1, function ($timerId, $args) use ($id) {
            //     var_dump($timerId, $args);
            //     Timer::clear(3);
            //     Timer::clearAll();
            // }, ['after123']);
            // $aid = Timer::after(3, function ($timerId, $args) {
            //     var_dump($timerId, $args);
            //     Timer::clearAll();
            // }, ['after']);
            // var_dump('afterid:' . $aid);
            $tid = Timer::tick(1.0, function ($timerId, $args) {
                var_dump($timerId, $args);
            }, ['test']);

            Timer::after(3, function () use ($tid) {
                var_dump('just once');
                Timer::clear($tid);
            });
        });

        $server->on('receive', function (Server $server, TcpConnection $conn, string $data) {
            var_dump($server->send($conn, 'from server'));
        });

        $server->on('close', function (Server $server, TcpConnection $conn) {
            var_dump('客户端下线');
        });

        $server->start();
        return Command::SUCCESS;
    }
}