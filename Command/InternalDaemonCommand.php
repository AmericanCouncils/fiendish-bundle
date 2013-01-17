<?php
namespace Beatbox\DaemonBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class InternalDaemonCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('beatbox:internal-daemon')
            ->setDescription('Used by Beatbox internally to start sub-daemons')
            ->addArgument(
                'daemonSpec',
                InputArgument::REQUIRED
            )
            ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getApplication()->getKernel()->getContainer();
        $daemonSpec = json_decode($input->getArgument('daemonSpec'));
        $daemonName = $daemonSpec->{"daemonName"};
        $daemonClass = $daemonSpec->{"daemonClass"};

        print("Starting daemon " . $daemonName . ".\n");
        $d = new $daemonClass($daemonName, $container);
        $d->run($daemonSpec->{"initialState"});
    }
}
