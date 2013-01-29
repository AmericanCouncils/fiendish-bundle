<?php

namespace DavidMikeSimon\FiendishBundle\Tests;

use DavidMikeSimon\FiendishBundle\Entity\Process;

class ProcessTest extends FiendishTestCase
{
    public function testProcessInitialSetup()
    {
        $em = $this->getContainer()->get('doctrine')->getEntityManager();

        $iniState = [
            "some_number" => 42,
            "another_number" => 18.5,
            "now_a_string" => "yep i am a string",
            "nesting_is" => [
                "fun",
                "exciting",
                "supercilious",
                "step 1 of our 3 step plan",
                "but don't ask about step 2",
                "we don't know what this \"step 2\" is",
                "step 3 is profit though"
            ]
        ];
        $proc = new Process(
            parent::GROUP_NAME,
            'test_daemon',
            'Foo/Bar',
            $iniState
        );
        $this->assertFalse($proc->isSetup());
        $em->persist($proc);
        $em->flush();

        $appPath = __DIR__; // Not really a symfony app dir, but ok for test
        $this->assertFalse($proc->isSetup());
        $proc->initialSetup($appPath);
        $this->assertTrue($proc->isSetup());

        $this->assertContains("test_daemon", $proc->getProcName());
        $this->assertContains((string)($proc->getId()), $proc->getProcName());

        $phpExecPath = explode(" ", $proc->getCommand())[0];
        $this->assertFileExists($phpExecPath);
        $this->assertContains("php", $phpExecPath);

        $appConsolePath = explode(" ", $proc->getCommand())[1];
        $this->assertEquals($appPath . "/console", $appConsolePath);

        $consoleCmd = "fiendish:internal-daemon";
        $this->assertContains($consoleCmd, $proc->getCommand());
        $daemonSpecJsonShellEsc = trim(substr( // Get only the JSON part of the command
            $proc->getCommand(),
            strpos($proc->getCommand(), $consoleCmd) + strlen($consoleCmd)
        ));
        $daemonSpecJson = `echo $daemonSpecJsonShellEsc`; // Remove shell escapes
        $daemonSpec = json_decode($daemonSpecJson);

        $this->assertEquals(parent::GROUP_NAME, $daemonSpec->groupName);
        $this->assertEquals("Foo/Bar", $daemonSpec->daemonClass);
        $this->assertEquals("test_daemon", $daemonSpec->daemonName);
        // PHP JSON conversion turns assoc. arrays into objects, which would break
        // a straight comparison to $iniState.
        $jsonifiedIniState = json_decode(json_encode($iniState));
        $this->assertEquals($jsonifiedIniState, $daemonSpec->initialState);
    }

    public function testInvalidSetup() {
        $proc = new Process(
            parent::GROUP_NAME,
            'test_daemon',
            'Foo/Bar',
            []
        );

        $this->setExpectedException('LogicException', 'persist');
        // ERROR: Have to persist first before calling initialSetup
        $proc->initialSetup(__DIR__);
    }

    public function testEarlyProcNameGet() {
        $proc = new Process(
            parent::GROUP_NAME,
            'test_daemon',
            'Foo/Bar',
            []
        );

        $this->setExpectedException('LogicException', 'un-setup');
        // ERROR: Have to persist first before getting proc name
        $proc->getProcName();
    }

    public function testEarlyFullProcNameGet() {
        $proc = new Process(
            parent::GROUP_NAME,
            'test_daemon',
            'Foo/Bar',
            []
        );

        $this->setExpectedException('LogicException', 'un-setup');
        // ERROR: Have to persist first before getting proc name
        $proc->getFullProcName();
    }

    public function testEarlyCommandGet() {
        $proc = new Process(
            parent::GROUP_NAME,
            'test_daemon',
            'Foo/Bar',
            []
        );

        $this->setExpectedException('LogicException', 'un-setup');
        // ERROR: Have to persist first before getting proc name
        $proc->getCommand();
    }
}
