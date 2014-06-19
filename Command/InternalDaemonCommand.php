<?php

namespace AC\FiendishBundle\Command;

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
        try {
            $container = $this->getApplication()->getKernel()->getContainer();
            $daemonClass = $input->getArgument('daemonClass');

            $spec = json_decode($input->getArgument('jsonSpec'), true);
            if (json_last_error() != JSON_ERROR_NONE) {
                throw new \RuntimeException(
                    "JSON daemon spec parse failed: " . json_last_error_msg()
                );
            }
            if (!isset($spec['groupName'])) {
                throw new \RuntimeException("No group name in daemon spec");
            }
            if (!isset($spec['procName'])) {
                throw new \RuntimeException("No proc name in daemon spec");
            }
            if (!isset($spec['arg'])) {
                throw new \RuntimeException("No arg field in daemon spec");
            }
            print("Starting daemon '" . $spec['procName'] . "'\n");

            $d = new $daemonClass($spec['groupName'], $spec['procName'], $container);
            $d->run($spec['arg']);
        } catch (\Exception $e) {
            print("Exception in daemon\n");
            print($e);
            print($e->getTraceAsString());
            throw $e;
        }
    }
}
