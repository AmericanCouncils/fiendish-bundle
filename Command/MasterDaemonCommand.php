<?php

namespace DavidMikeSimon\FiendishBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use DavidMikeSimon\FiendishBundle\Daemon\MasterDaemon;

class MasterDaemonCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('fiendish:master-daemon')
            ->setDescription('Starts the master process management daemon')
            ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        print("Starting master daemon.\n");
        $d = new MasterDaemon("master", $this->getApplication()->getKernel()->getContainer());
        $d->run();
    }
}
