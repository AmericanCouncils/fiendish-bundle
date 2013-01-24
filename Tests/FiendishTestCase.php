<?php

namespace DavidMikeSimon\FiendishBundle\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Doctrine\Bundle\MigrationsBundle\Command\MigrationsMigrateDoctrineCommand;

abstract class FiendishTestCase extends WebTestCase
{
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
        $this->runSymfonyCommand("doctrine:migrations:migrate 0");
        $this->runSymfonyCommand("doctrine:migrations:migrate");

        parent::setUp();
    }

    public function tearDown()
    {
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
}
