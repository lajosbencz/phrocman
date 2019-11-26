<?php


use Phrocman\Descriptor;
use PHPUnit\Framework\TestCase;

class DescriptorTest extends TestCase
{
    public function provideServices()
    {
        return [
            ['test', 'echo foo', 3, __DIR__, ['baz'=>'bax']],
        ];
    }

    /**
     * @dataProvider provideServices
     * @param $tag
     * @param $cmd
     * @param $cnt
     * @param $cwd
     * @param $env
     */
    public function testService($tag, $cmd, $cnt, $cwd, $env)
    {
        $i = new Descriptor\Service($tag, $cmd, $cnt, $cwd, $env);
        $this->assertEquals($tag, $i->getTag());
        $this->assertEquals($cmd, $i->getCmd());
        $this->assertEquals($cnt, $i->getCnt());
        $this->assertEquals($cwd, $i->getCwd());
        $this->assertEquals($env, $i->getEnv());
    }
}
