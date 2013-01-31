<?php

namespace DavidMikeSimon\FiendishBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use DavidMikeSimon\FiendishBundle\Daemon\MasterDaemon;

/**
 * Command that starts the manager for your daemon processes
 */
class MasterDaemonCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('fiendish:master-daemon')
            ->setDescription('Starts the master process management daemon')
            ->addArgument(
                'group',
                InputArgument::REQUIRED
            )
            ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        print("Starting master daemon.\n");
        $d = new MasterDaemon("master", $this->getApplication()->getKernel()->getContainer());
        $internalState = (object)["group" => $input->getArgument('group')];
        $d->run($internalState);
    }
}
