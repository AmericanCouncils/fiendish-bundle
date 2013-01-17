<?php

namespace Beatbox\DaemonBundle\Daemon;

use Beatbox\ModelBundle\Entity\Noise;

class NoiseGeneratorDaemon extends Daemon
{
    private $chamberId;
    private $sound;

    public function run($initialState = null)
    {
        $this->chamberId = $initialState->{"chamberId"};
        $this->sound = $initialState->{"sound"};

        $em = $this->getContainer()->get('doctrine')->getManager();
        $chamber = $em->getRepository('BeatboxModelBundle:Chamber')
            ->find($this->chamberId);

        $n = 0;
        while (true) {
            $noise_repo = $em->getRepository('BeatboxModelBundle:Noise');

            $n += 1;
            if ($n == 100) { $n = 1; }
            $noise = new Noise();
            $noise->setChamber($chamber);
            $noise->setSound($this->sound . " " . $n);
            $em->persist($noise);
            $em->flush();

            $this->heartbeat();
            sleep(2);
        }
    }
}
