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
    private static $supervisorClient = null;

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
        $supervisor = self::getSupervisorClient();
        foreach($supervisor->getAllProcessInfo() as $proc) {
            $is_master = $proc["name"] == self::GROUP_NAME . "_master";
            $is_grouped = $proc["group"] == self::GROUP_NAME;
            $is_running = $proc["statename"] == "RUNNING";
            if ($is_running && ($is_master || $is_grouped)) {
                $supervisor->stopProcess($proc["name"]);
            }
            if ($is_grouped) {
                $supervisor->removeProcessFromGroup(self::GROUP_NAME, $proc["name"]);
            }
        }

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

        parent::setUp();
    }

    protected function requiresMaster()
    {
        $supervisor = self::getSupervisorClient();
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

    protected static function getSupervisorClient()
    {
        if (is_null(self::$supervisorClient)) {
            self::$supervisorClient =  new SupervisorClient("unix:///var/run/supervisor.sock", 0, 10);
        }
        return self::$supervisorClient;
    }
}
