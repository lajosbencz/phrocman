<?php

namespace Phrocman;


use DateTime;

class CronUnit
{
    const TYPE_SIMPLE = 'x';
    const TYPE_WILDCARD = '*';
    const TYPE_MODULO = '/';
    const TYPE_LIST = ',';

    protected $type = self::TYPE_WILDCARD;
    protected $stringValue = self::TYPE_WILDCARD;
    protected $value;

    public function __construct(string $value)
    {
        $this->stringValue = $value;
        if(strpos($value, self::TYPE_WILDCARD) !== false) {
            $this->type = $this->value = self::TYPE_WILDCARD;
            return;
        }
        if(strpos($value, self::TYPE_MODULO) !== false) {
            $parts = explode(self::TYPE_MODULO, $value);
            $this->type = self::TYPE_MODULO;
            $this->value = [
                'offset' => intval($parts[0]),
                'modulo' => intval($parts[1]),
            ];
            return;
        }
        if(strpos($value, self::TYPE_LIST) !== false) {
            $this->type = self::TYPE_LIST;
            $this->value = explode(self::TYPE_LIST, $value);
            return;
        }
        $this->type = self::TYPE_SIMPLE;
        $this->value = intval($value);
    }

    public function getType()
    {
        return $this->type;
    }

    public function isTypeSimple() : bool
    {
        return $this->type == self::TYPE_SIMPLE;
    }

    public function isTypeWildcard() : bool
    {
        return $this->type == self::TYPE_WILDCARD;
    }

    public function isTypeModulo() : bool
    {
        return $this->type == self::TYPE_MODULO;
    }

    public function isTypeList() : bool
    {
        return $this->type == self::TYPE_LIST;
    }

    public function check($value): bool
    {
        if(!$this->isTypeWildcard()) {
            switch(true) {
                default:
                case $this->isTypeSimple():
                    if($value != $this->getValue()) {
                        return false;
                    }
                    break;
                case $this->isTypeList():
                    if(!in_array($value, $this->getValue())) {
                        return false;
                    }
                    break;
                case $this->isTypeModulo():
                    $v = $this->getValue();
                    $o = $v['offset'];
                    $m = $v['modulo'];
                    if((($value - $o) % $m) !== 0) {
                        return false;
                    }
                    break;
            }
        }
        return true;
    }

    /**
     * @return string|int|array
     */
    public function getValue()
    {
        return $this->value;
    }

    public function getStringValue(): string
    {
        return $this->stringValue;
    }

    public function __toString()
    {
        return $this->getStringValue();
    }
}
