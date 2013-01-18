<?php

namespace DavidMikeSimon\FiendishBundle\Tests\Fixtures\Controller;

use Symfony\Component\HttpFoundation\Response;

class TestController
{
    public function indexAction()
    {
        return new Response('tests');
    }
}
