<?php

namespace DavidMikeSimon\FiendishBundle\Tests\Entity;

use DavidMikeSimon\FiendishBundle\Tests\FiendishTestCase;
use DavidMikeSimon\FiendishBundle\Entity\Process;

class ProcessTest extends FiendishTestCase
{
    public function testSimple()
    {
        $container = $this->getContainer();
        $em = $container->get('doctrine')->getEntityManager();
        $proc = new Process('fiendish_test', 'test_daemon', 'Test/Foo', []);
        $em->persist($proc);
        $em->flush();
    }
}
