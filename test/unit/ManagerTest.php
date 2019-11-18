<?php

use Phrocman\Manager;
use PHPUnit\Framework\TestCase;
use React\ChildProcess\Process;

class ManagerTest extends TestCase
{
    public function testManager()
    {
        ob_start();
        $man = new Manager;
        $man->addService('php '.dirname(__DIR__).'/payload.php --r 5 --f 0.1 test string');
        $man->getLoop()->addTimer(6, function() use($man) {
            $man->stop();
        });
        $man->run();
        $out = ob_get_clean();
        $this->assertStringContainsString('test string', $out);
    }
}
