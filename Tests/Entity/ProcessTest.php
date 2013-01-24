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
        $qb = $em
            ->getRepository('DavidMikeSimonFiendishBundle:Process')
            ->createQueryBuilder('process');
        $qb->select('count(process.id)');

        $this->assertEquals(0, $qb->getQuery()->getSingleScalarResult());
        $proc = new Process('fiendish_test', 'test_daemon', 'Test/Foo', []);
        $em->persist($proc);
        $em->flush();
        $this->assertEquals(1, $qb->getQuery()->getSingleScalarResult());
    }

    public function testSimple2()
    {
        $container = $this->getContainer();
        $em = $container->get('doctrine')->getEntityManager();
        $qb = $em
            ->getRepository('DavidMikeSimonFiendishBundle:Process')
            ->createQueryBuilder('process');
        $qb->select('count(process.id)');

        $this->assertEquals(0, $qb->getQuery()->getSingleScalarResult());
        $proc = new Process('fiendish_test', 'test_daemon', 'Test/Foo', []);
        $em->persist($proc);
        $em->flush();
        $this->assertEquals(1, $qb->getQuery()->getSingleScalarResult());
    }
}
