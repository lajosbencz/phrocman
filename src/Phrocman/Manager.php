<?php

namespace Phrocman;


use DateTime;
use Evenement\EventEmitterInterface;
use Evenement\EventEmitterTrait;
use React\ChildProcess\Process;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;

class Manager implements EventsAwareInterface
{
    /** @var LoopInterface */
    protected $loop;

    /** @var EventEmitterInterface */
    protected $eventsManager;

    /** @var Group */
    protected $group;

    public function __construct(Group $group, ?EventsManager $eventsManager=null, ?LoopInterface $loop=null)
    {
        $this->setEventsManager($eventsManager ? : new EventsManager);
        $group->setManager($this);
        foreach(Group::EVENT_TOPIC_LIST as $event) {
            $group->on($event, function(...$args) use($event) {
                $this->getEventsManager()->emit($event, $args);
            });
        }
        $this->group = $group;
        $this->loop = $loop ?? Factory::create();
    }

    public function start(): void
    {
        $lastTime = 0;
        $this->loop->addSignal(SIGINT, function() {
            $this->stop();
            $this->loop->stop();
        });
        $this->loop->addPeriodicTimer(0.05, function () use (&$lastTime) {
            $time = microtime(true);
            $timeSec = floor($time);
            if ($timeSec > $lastTime) {
                $now = \DateTime::createFromFormat('U.u', sprintf('%0.6f', $time));
                $this->group->tickSecond($now);
                $this->getEventsManager()->emit('tick', [$now]);
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

    public function setEventsManager(EventEmitterInterface $eventsManager): void
    {
        $this->eventsManager = $eventsManager;
    }

    public function getEventsManager(): EventEmitterInterface
    {
        return $this->eventsManager;
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
