<?php

namespace Phrocman;


use DateTime;
use Evenement\EventEmitterTrait;
use React\ChildProcess\Process;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;

class Manager extends Group
{
    /** @var LoopInterface */
    protected $loop;

    public function __construct(string $name='Phrocman', ?LoopInterface $loop=null)
    {
        $this->loop = $loop ?? Factory::create();
        parent::__construct($name);
    }

    public function tickSecond(DateTime $dateTime): void
    {
        $this->emit('tick', [$dateTime]);
        parent::tickSecond($dateTime);
    }

    public function start(): void
    {
        $lastTime = 0;
        $this->loop->addPeriodicTimer(0.05, function () use (&$lastTime) {
            $time = microtime(true);
            $timeSec = floor($time);
            if ($timeSec > $lastTime) {
                $now = \DateTime::createFromFormat('U.u', sprintf('%0.6f', $time));
                $this->tickSecond($now);
                $lastTime = $timeSec;
            }
        });
        parent::start();
        $this->loop->run();
    }

    public function stop(): void
    {
        parent::stop();
    }

    /**
     * @return LoopInterface
     */
    public function getLoop(): LoopInterface
    {
        return $this->loop;
    }

}
