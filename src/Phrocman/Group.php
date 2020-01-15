<?php

namespace Phrocman;

use Evenement\EventEmitterInterface;
use Evenement\EventEmitterTrait;
use Phrocman\Runnable\Service;
use Phrocman\Runnable\Timer;

class Group implements RunnableInterface, UidInterface, EventsAwareInterface
{
    const EVENT_TOPIC_LIST = ['start', 'trigger', 'stdout', 'stderr', 'exit', 'fail', 'stop'];

    use UidTrait, EventEmitterTrait;

    /** @var Manager */
    protected $manager = null;

    /** @var self|null */
    protected $parent = null;

    /** @var string */
    protected $name;

    /** @var self[] */
    protected $children = [];

    /** @var Service[] */
    protected $services = [];

    /** @var Timer[] */
    protected $timers = [];

    protected function add(&$arr, $item, ?int $index=null)
    {
        if($index === null) {
            array_push($arr, $item);
        } else {
            if(count($arr) < $index) {
                throw new \InvalidArgumentException('index out of bounds');
            }
            array_splice($arr, $index, 0, $item);
        }
    }

    public function __construct(string $name, ?self $parent=null)
    {
        $this->generateUid();
        $this->name = $name;
        if($parent) {
            $this->setParent($parent);
            $parent->addChild($this);
        }
    }

    public function setManager(Manager $manager) {
        $this->manager = $manager;
        return $this;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setParent(?self $parent): void
    {
        $this->parent = $parent;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function getManager(): Manager
    {
        if($this->parent) {
            return $this->parent->getManager();
        }
        return $this->manager;
    }

    function setEventsManager(EventEmitterInterface $eventsManager): void
    {
        $this->getManager()->setEventsManager($eventsManager);
    }

    function getEventsManager(): EventEmitterInterface
    {
        return $this->getManager()->getEventsManager();
    }

    public function addChild(self $group, ?int $index=null): self
    {
        $group->setParent($this);
        $this->add($this->children, $group, $index);
        foreach(self::EVENT_TOPIC_LIST as $event) {
            $group->on($event, function(...$args) use($event) {
                $this->emit($event, $args);
                if($event=='stdout' || $event == 'stderr') {
                    array_shift($args);
                    array_unshift($args, $this);
                    $this->emit($event, $args);
                }
            });
        }
        return $this;
    }

    /**
     * @return \Generator|self[]
     */
    public function getChildren(): \Generator
    {
        foreach ($this->children as $child) {
            yield $child;
        }
    }

    public function addService(string $name, string $cmd, string $cwd = '', array $env = [], int $instanceCount = 1, bool $keepAlive=true, array $validExitCodes=[]): Service
    {
        $item = new Service($this, $name, $cmd, $cwd, $env, $instanceCount, $keepAlive, $validExitCodes);
        $this->add($this->services, $item);
        $item->on('start', function($what) {
            $this->emit('start', [$what]);
        });
        $item->on('stdout', function($what, $data) {
            $this->emit('stdout', [$what, $data]);
            $this->emit('stdout', [$this, $data]);
        });
        $item->on('stderr', function($what, $data) {
            $this->emit('stderr', [$what, $data]);
            $this->emit('stderr', [$this, $data]);
        });
        $item->on('fail', function($what, $code) {
            $this->emit('fail', [$what, $code]);
        });
        $item->on('exit', function($what, $code) {
            $this->emit('exit', [$what, $code]);
        });
        $item->on('stop', function($what) {
            $this->emit('stop', [$what]);
        });
        return $item;
    }

    /**
     * @return \Generator|Runnable\Service[]
     */
    public function getServices(): \Generator
    {
        foreach($this->services as $service) {
            yield $service;
        }
    }

    public function addTimer(string $name, Cron $cron, string $cmd, string $cwd = '', array $env = []): Timer
    {
        $item = new Timer($this, $name, $cron, $cmd, $cwd, $env);
        $this->add($this->timers, $item);
        $item->on('start', function() use($item) {
            $this->emit('start', [$item]);
        });
        $item->on('stdout', function( $data) use($item) {
            $this->emit('stdout', [$item, $data]);
        });
        $item->on('stderr', function( $data) use($item) {
            $this->emit('stderr', [$item, $data]);
        });
        $item->on('trigger', function() use($item) {
            $this->emit('trigger', [$item]);
        });
        $item->on('exit', function($code) use($item) {
            $this->emit('exit', [$item, $code]);
        });
        $item->on('stop', function() use($item) {
            $this->emit('stop', [$item]);
        });
        return $item;
    }

    /**
     * @return \Generator|Runnable\Timer[]
     */
    public function getTimers(): \Generator
    {
        foreach($this->timers as $timer) {
            yield $timer;
        }
    }

    public function tickSecond(\DateTime $dateTime): void
    {
        foreach($this->children as $child) {
            $child->tickSecond($dateTime);
        }
        foreach($this->timers as $timer) {
            $timer->tickSecond($dateTime);
        }
    }

    /**
     * @return \Generator|RunnableInterface[]
     */
    protected function iterate(): \Generator
    {
        foreach($this->getServices() as $runnable) {
            yield $runnable;
        }
        foreach($this->getTimers() as $runnable) {
            yield $runnable;
        }
        foreach($this->getChildren() as $runnable) {
            yield $runnable;
        }
    }

    public function start(): void
    {
        foreach($this->iterate() as $runnable) {
            $runnable->start();
        }
        $this->emit('start', [$this]);
    }

    public function stop(): void
    {
        foreach($this->iterate() as $runnable) {
            $runnable->stop();
        }
        $this->emit('stop', [$this]);
    }

    public function restart(): void
    {
        foreach($this->iterate() as $runnable) {
            $runnable->stop();
        }
        $this->emit('stop', [$this]);
        foreach($this->iterate() as $runnable) {
            $runnable->start();
        }
        $this->emit('start', [$this]);
    }

    public function isRunning(): bool
    {
        foreach($this->iterate() as $item) {
            if(!$item->isRunning()) {
                return false;
            }
        }
        return false;
    }

    public function findGroup(string $uid): ?self
    {
        if($this->getUid() === $uid) {
            return $this;
        }
        foreach($this->children as $child) {
            if($child->getUid() === $uid) {
                return $child;
            }
            if($f = $child->findGroup($uid)) {
                return $f;
            }
        }
        return null;
    }

    public function findService(string $uid): ?Runnable\Service
    {
        foreach($this->services as $service) {
            if($service->getUid() === $uid) {
                return $service;
            }
        }
        foreach($this->children as $child) {
            if($f = $child->findService($uid)) {
                return $f;
            }
        }
        return null;
    }

    public function findTimer(string $uid): ?Runnable\Timer
    {
        foreach($this->timers as $timer) {
            if($timer->getUid() === $uid) {
                return $timer;
            }
        }
        foreach($this->children as $child) {
            if($f = $child->findTimer($uid)) {
                return $f;
            }
        }
        return null;
    }

    public function getParentList(): array
    {
        $parents = [];
        $parent = $this->getParent();
        while($parent) {
            array_unshift($parents, [
                'uid' => $parent->getUid(),
                'name' => $parent->getName(),
            ]);
            $parent = $parent->getParent();
        }
        return $parents;
    }

    public function toArray(): array
    {
        $a = [
            'type' => 'group',
            'name' => $this->getName(),
            'uid' => $this->getUid(),
            'running' => $this->isRunning(),
            'parents' => $this->getParentList(),
            'children' => [],
            'services' => [],
            'timers' => [],
        ];
        foreach($this->children as $child) {
            $a['children'][] = $child->toArray();
        }
        foreacH($this->services as $service) {
            $a['services'][] = $service->toArray();
        }
        foreacH($this->timers as $timer) {
            $a['timers'][] = $timer->toArray();
        }
        return $a;
    }

}