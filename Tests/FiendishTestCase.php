<?php

namespace DavidMikeSimon\FiendishBundle\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Kernel;

abstract class FiendishTestCase extends WebTestCase
{
    protected function deleteTmpDir()
    {
        if (!file_exists($dir = sys_get_temp_dir().'/'.Kernel::VERSION)) {
            return;
        }

        $fs = new Filesystem();
        $fs->remove($dir);
    }

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
        parent::setUp();
        $this->deleteTmpDir();
    }
}
