<?php

namespace DavidMikeSimon\FiendishBundle\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Doctrine\Bundle\MigrationsBundle\Command\MigrationsMigrateDoctrineCommand;
use Doctrine\DBAL\DBALException;
use SupervisorClient\SupervisorClient;

abstract class FiendishTestCase extends WebTestCase
{
    const GROUP_NAME = "testfiendish";

    private static $migrated = false;

    protected function getContainer(array $options = array())
    {
        if (!static::$kernel) {
            static::$kernel = static::createKernel($options);
        }
        static::$kernel->boot();

        return static::$kernel->getContainer();
    }

    protected static function getKernelClass()
    {
        require_once __DIR__.'/Fixtures/app/AppKernel.php';

        return 'DavidMikeSimon\FiendishBundle\Tests\Functional\AppKernel';
    }

    protected static function createKernel(array $options = array())
    {
        $class = self::getKernelClass();

        return new $class(
            'test',
            isset($options['debug']) ? $options['debug'] : true
        );
    }

    public function setUp()
    {
        if (!self::$migrated) {
            // FIXME: Ugly, we should avoid using a static variable to do this
            $this->runSymfonyCommand("doctrine:migrations:migrate 0");
            $this->runSymfonyCommand("doctrine:migrations:migrate");
            self::$migrated = true;
        } else {
            $conn = $this->getContainer()->get("doctrine")->getConnection() ;
            $sm = $conn->getSchemaManager();
            foreach ($sm->listTables() as $table) {
                if ($table->getName() != "migration_versions") {
                    $conn->exec("DELETE FROM " . $table->getName());
                }
            }
        }

        $supervisor = $this->getSupervisorClient();
        $proc_info = $supervisor->getProcessInfo(self::GROUP_NAME . "_master");
        if ($proc_info["statename"] == "RUNNING") {
            $supervisor->stopProcess(self::GROUP_NAME . "_master");
        }
        $supervisor->stopProcessGroup(self::GROUP_NAME);

        parent::setUp();
    }

    protected function requiresMaster()
    {
        $supervisor = $this->getSupervisorClient();
        $proc_info = $supervisor->getProcessInfo(self::GROUP_NAME . "_master");
        if ($proc_info["statename"] == "STOPPED") {
            $supervisor->startProcess(self::GROUP_NAME . "_master");
        } else {
            throw new \Exception("Required master for this test, but it was already running");
        }
    }

    protected function runSymfonyCommand($cmd)
    {
        $full_cmd =
            'php ' .
            $this->getContainer()->get('kernel')->getRootDir() .  '/console ' .
            $cmd .
            ' --no-interaction';
        $proc = new Process($full_cmd);
        $proc->run();
        $err = trim($proc->getErrorOutput());
        if ($err != "") {
            print("\n#########\n");
            print("Error running " . $full_cmd . ":\n");
            print $err;
            print("\n#########\n");
            print $proc->getOutput();
            print("\n#########\n");
            throw new \Exception("Symfony command error");
        }
    }

    protected function getSupervisorClient()
    {
        return new SupervisorClient("unix:///var/run/supervisor.sock", 0, 10);
    }
}
