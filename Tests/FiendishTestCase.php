<?php

namespace AC\FiendishBundle\Tests;

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

    // This is the closest I can get to a constant array in PHP :-P
    public static function getStoppedStates()
    {
        return ["STOPPED", "FATAL", "EXITED", "UNKNOWN"];
    }

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

        return 'AC\FiendishBundle\Tests\Functional\AppKernel';
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
        if (posix_getuid() != 0) {
            print "You must run these tests with sudo!\n";
            print "The tests do nasty things to the supervisor daemon, so you";
            print "should run the tests in a scratch VM like Vagrant too.";
            die();
        }

        $this->killAllSupervisorProcs();

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

    protected function killAllSupervisorProcs()
    {
        $supervisor = self::getSupervisorClient();
        $cycle = 1;
        while (true) {
            // There are various states a process can be in while Supervisor
            // tries to restart it. We can only stop processes that are
            // RUNNING, and only remove processes that are STOPPED. Any
            // other states, we have to wait for them to change.
            $weirdStatesCount = 0;

            foreach($supervisor->getAllProcessInfo() as $proc) {
                $is_master = $proc["name"] == self::GROUP_NAME . "_master";
                $is_grouped = $proc["group"] == self::GROUP_NAME;
                $is_stoppable = $proc["statename"] == "RUNNING";
                $is_removable = in_array($proc["statename"], self::getStoppedStates());

                if (!($is_master || $is_grouped)) {
                    // This process isn't one of ours
                    continue;
                }

                if ($is_stoppable) {
                    $tgt_name = $proc["name"];
                    if ($is_grouped) {
                        $tgt_name = $proc["group"] . ":" . $tgt_name;
                    }
                    $supervisor->stopProcess($tgt_name);
                    // The stopProcess call blocks until process is dead
                    $is_removable = true;
                }

                if ($is_removable && $is_grouped) {
                    $supervisor->removeProcessFromGroup(self::GROUP_NAME, $proc["name"]);
                }

                if (!($is_removable || $is_stoppable)) {
                    ++$weirdStatesCount;
                }
            }

            if ($weirdStatesCount == 0) {
                break;
            }
            ++$cycle;
            if ($cycle > 50) {
                print("-----\n");
                print("SUPERVISOR STATE:\n");
                var_dump($supervisor->getAllProcessInfo());
                print("\n-----\n\n");
                throw new \Exception("Unable to clear supervisor state for test group");
            }
            usleep(1000 * 300); // 300 ms
        }
    }

    protected function requiresMaster()
    {
        $supervisor = self::getSupervisorClient();
        $proc_info = $supervisor->getProcessInfo(self::GROUP_NAME . "_master");
        if (in_array($proc_info["statename"], self::getStoppedStates())) {
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

    protected function getGroup()
    {
        return $this->getContainer()->get(
            'fiendish.groups.' . self::GROUP_NAME
        );
    }
}
