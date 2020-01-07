<?php

namespace Phrocman;


use DateTime;
use Evenement\EventEmitterInterface;
use Evenement\EventEmitterTrait;
use React\ChildProcess\Process;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;

class Manager implements EventEmitterInterface
{
    /** @var LoopInterface */
    protected $loop;

    /** @var EventsManager */
    protected $eventsManager;

    /** @var Group */
    protected $group;

    public function __construct(Group $group, ?LoopInterface $loop=null)
    {
        $this->setEventsManager(new EventsManager);
        $group->setManager($this);
        $this->group = $group;
        $this->loop = $loop ?? Factory::create();
    }

    public function tickSecond(DateTime $dateTime): void
    {
        $this->emit('tick', [$dateTime]);
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
        $this->group->start();
        $this->loop->run();
    }

    public function stop(): void
    {
        $this->group->stop();
        $this->loop->stop();
    }

    public function getGroup(): Group
    {
        return $this->group;
    }

    public function getLoop(): LoopInterface
    {
        return $this->loop;
    }

    public function setEventsManager(EventsManager $eventsManager): void
    {
        $this->eventsManager = $eventsManager;
    }

    public function getEventsManager(): EventsManager
    {
        return $this->eventsManager;
    }

    public function on($event, callable $listener)
    {
        return $this->eventsManager->on($event, $listener);
    }

    public function once($event, callable $listener)
    {
        return $this->eventsManager->once($event, $listener);
    }

    public function removeListener($event, callable $listener): void
    {
        $this->eventsManager->removeListener($event, $listener);
    }

    public function removeAllListeners($event = null): void
    {
        $this->eventsManager->removeAllListeners($event);
    }

    public function listeners($event = null): array
    {
        return $this->eventsManager->listeners($event);
    }

    public function emit($event, array $arguments = []): void
    {
        $this->eventsManager->emit($event, $arguments);
    }

    public function findGroup(string $uid): ?Group
    {
        return $this->group->findGroup($uid);
    }

    public function findService(string $uid): ?Runnable\Service
    {
        return $this->group->findService($uid);
    }

    public function findTimer(string $uid): ?Runnable\Timer
    {
        return $this->group->findTimer($uid);
    }

    public function toArray()
    {
        return $this->group->toArray();
    }

}
