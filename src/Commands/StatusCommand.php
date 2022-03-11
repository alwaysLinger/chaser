<?php


namespace Al\Chaser\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

// TODO
class StatusCommand extends Command
{
    protected function configure()
    {
        $this->setName('status')
            ->setDescription('display server status')
            ->setHelp("This command allows you to create users...");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        app('log')->debug('abcd');
        return 1;
    }
}