<?php

namespace Phrocman;


use DateTime;

class Cron
{
    /*
     * Wildcard: *
     * Modulus M with offset O: O/M
     * Value list: V,V,V
     * Value: V
     * Off: -
     */

    protected $weekdays;
    protected $months;
    protected $days;
    protected $hours;
    protected $minutes;
    protected $seconds;

    public function __construct(
        string $weekdays = CronUnit::TYPE_WILDCARD,
        string $months = CronUnit::TYPE_WILDCARD,
        string $days = CronUnit::TYPE_WILDCARD,
        string $hours = CronUnit::TYPE_WILDCARD,
        string $minutes = '0',
        string $seconds = '0'
    )
    {
        $this->weekdays = new CronUnit($weekdays);
        $this->months = new CronUnit($months);
        $this->days = new CronUnit($days);
        $this->hours = new CronUnit($hours);
        $this->minutes = new CronUnit($minutes);
        $this->seconds = new CronUnit($seconds);
    }

    public function check(?DateTime $dateTime=null): bool
    {
        if(!$dateTime) {
            $dateTime = new DateTime;
        }
        return
            $this->weekdays->check(intval($dateTime->format('N'))) &&
            $this->months->check(intval($dateTime->format('n'))) &&
            $this->days->check(intval($dateTime->format('j'))) &&
            $this->hours->check(intval($dateTime->format('G'))) &&
            $this->minutes->check(intval($dateTime->format('i'))) &&
            $this->seconds->check(intval($dateTime->format('s')))
        ;
    }

    public function __toString()
    {
        return "{$this->weekdays}\t{$this->months}\t{$this->days}\t{$this->hours}\t{$this->minutes}\t{$this->seconds}";
    }

    public function toArray()
    {
        return (array)$this;
    }
}
