<?php

use Phrocman\CronUnit;
use PHPUnit\Framework\TestCase;

class CronUnitTest extends TestCase
{
    public function provideTypes()
    {
        return [
            ['*', CronUnit::TYPE_WILDCARD],
            ['0', CronUnit::TYPE_SIMPLE],
            ['0,1', CronUnit::TYPE_LIST],
            ['0/0', CronUnit::TYPE_MODULO],
        ];
    }

    /**
     * @dataProvider provideTypes
     * @param $value
     * @param $type
     */
    public function testType($value, $type)
    {
        $u = new CronUnit($value);
        $this->assertEquals($type, $u->getType());
        if($u->getType() == CronUnit::TYPE_WILDCARD) {
            $this->assertTrue($u->isTypeWildcard());
            $this->assertFalse($u->isTypeSimple());
            $this->assertFalse($u->isTypeList());
            $this->assertFalse($u->isTypeModulo());
        }
        elseif($u->getType() == CronUnit::TYPE_SIMPLE) {
            $this->assertFalse($u->isTypeWildcard());
            $this->assertTrue($u->isTypeSimple());
            $this->assertFalse($u->isTypeList());
            $this->assertFalse($u->isTypeModulo());
        }
        elseif($u->getType() == CronUnit::TYPE_LIST) {
            $this->assertFalse($u->isTypeWildcard());
            $this->assertFalse($u->isTypeSimple());
            $this->assertTrue($u->isTypeList());
            $this->assertFalse($u->isTypeModulo());
        }
        elseif($u->getType() == CronUnit::TYPE_MODULO) {
            $this->assertFalse($u->isTypeWildcard());
            $this->assertFalse($u->isTypeSimple());
            $this->assertFalse($u->isTypeList());
            $this->assertTrue($u->isTypeModulo());
        }
    }


    public function provideChecks()
    {
        return [
            [0, '*', true],
            [1, '*', true],
            [59, '*', true],

            [0, '0', true],
            [5, '0/5', true],
            [6, '1/5', true],
            [6, '3,6,9', true],

            [1, '0', false],
            [59, '0', false],
            [10, '0', false],
            [2, '0/5', false],
            [2, '1/5', false],
            [1, '3,6,9', false],
        ];
    }

    /**
     * @dataProvider provideChecks
     * @param $test
     * @param $value
     * @param $ok
     */
    public function testCheck($test, $value, $ok)
    {
        $u = new CronUnit($value);
        $this->assertEquals($ok, $u->check($test));
    }
}
