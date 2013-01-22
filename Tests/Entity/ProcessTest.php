<?php

namespace DavidMikeSimon\FiendishBundle\Tests\Entity;

use DavidMikeSimon\FiendishBundle\Tests\FiendishTestCase;

class ProcessTest extends FiendishTestCase
{
    public function testSimple()
    {
        $container = $this->getContainer();
        $this->assertEquals(3, 4);
    }
}
