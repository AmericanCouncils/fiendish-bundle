<?php

namespace DavidMikeSimon\FiendishBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class InternalDaemonCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('fiendish:internal-daemon')
            ->setDescription('Used internally to start sub-daemons')
            ->addArgument(
                'daemonClass',
                InputArgument::REQUIRED
            )
            ->addArgument(
                'jsonSpec',
                InputArgument::REQUIRED
            )
            ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getApplication()->getKernel()->getContainer();
        $daemonClass = $input->getArgument('daemonClass');
        $spec = json_decode($input->getArgument('jsonSpec'));

        print("Starting daemon '" . $spec->procName . "'\n");
        $d = new $daemonClass($spec->groupName, $spec->procName, $container);
        $d->run($spec->arg);
    }
}
