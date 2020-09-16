<?php


use Phrocman\Group;
use PHPUnit\Framework\TestCase;

class GroupTest extends TestCase
{
    public function testGetParentList()
    {
        $g1 = new Group('a');
        $g2 = new Group('b');
        $g3 = new Group('c');
        $g1->addChild($g2);
        $g2->addChild($g3);
        $list = $g3->getParentList();
        $this->assertIsArray($list);
        $this->assertCount(2, $list);
    }

    public function testGetPath()
    {
        $g1 = new Group('a');
        $g2 = new Group('b');
        $g3 = new Group('c');
        $g4 = new Group('d');
        $g1->addChild($g2);
        $g1->addChild($g3);
        $g2->addChild($g4);

        $this->assertEquals('a', $g1->getPath());
        $this->assertEquals('a/b', $g2->getPath());
        $this->assertEquals('a/c', $g3->getPath());
        $this->assertEquals('a/b/d', $g4->getPath());
    }

    public function testGetRoot()
    {
        $g1 = new Group('a');
        $g2 = new Group('b');
        $g3 = new Group('c');
        $g1->addChild($g2);
        $g2->addChild($g3);
        $this->assertEquals($g1, $g1->getRoot());
        $this->assertEquals($g1, $g2->getRoot());
        $this->assertEquals($g1, $g3->getRoot());
    }
}
