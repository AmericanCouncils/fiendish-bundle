<?php

namespace DavidMikeSimon\FiendishBundle\Tests\Entity;

use DavidMikeSimon\FiendishBundle\Tests\WebTestCase;

class ProcessTest extends WebTestCase
{
    public function testSimple()
    {
        $container = $this->getContainer();
        $this->assertEquals(3, 4);
    }
}
