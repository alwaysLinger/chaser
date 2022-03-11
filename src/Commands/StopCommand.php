<?php


namespace Al\Chaser\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

// TODO
class StopCommand extends Command
{
    protected function configure()
    {
        $this->setName('stop')
            ->setDescription('display server status')
            ->setHelp("This command allows you to create users...");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // app()->shutdownServer('tcp');
        return Command::SUCCESS;
    }
}