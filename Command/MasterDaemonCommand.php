<?php

namespace AC\FiendishBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use AC\FiendishBundle\Daemon\MasterDaemon;

/**
 * Command that starts the manager for your daemon processes.
 *
 * Run using the console command `fiendish:master-daemon mygroupname`.
 *
 * It's handy to just use Supervisor to start it up, since you'll be setting that
 * up anyways. However, if you do that, do _not_ put the master daemon
 * in the same Supervisor group that contains all your app's daemons.
 * The example supervisor config in the README follows this rule.
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
        $d = new MasterDaemon(
            $input->getArgument('group'),
            "master", // FIXME Inaccurate process name
            $this->getApplication()->getKernel()->getContainer()
        );
        try {
            $d->run(null);
        } catch (\Exception $e) {
            print("Exception in master daemon:\n");
            print($e);
            print($e->getTraceAsString());
            throw $e;
        }
    }
}
