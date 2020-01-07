<?php

use PHPUnit\Framework\TestCase;

class _uid {
    use \Phrocman\UidTrait;
    public function __construct()
    {
        $this->generateUid();
    }
}

class UidTest extends TestCase
{
    public function testUid()
    {
        $uid1 = new _uid;
        $uid2 = new _uid;
        $this->assertNotEquals($uid1->getUid(), $uid2->getUid());
    }
}
