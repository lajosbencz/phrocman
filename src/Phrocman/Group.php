<?php

namespace Phrocman;

use Evenement\EventEmitterTrait;
use Phrocman\Runnable\Service;
use Phrocman\Runnable\Timer;

class Group implements RunnableInterface, UidInterface
{
    use EventEmitterTrait, UidTrait;

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

    protected function stdListeners(Runnable $item)
    {
        $item->on('stdout', function($data) use($item) {
            $this->emit('stdout', [$data, $item, $this]);
        });
        $item->on('stderr', function($data) use($item) {
            $this->emit('stderr', [$data, $item, $this]);
        });
    }

    protected function serviceListeners(Runnable $item)
    {
        $this->stdListeners($item);
        $item->on('exit', function($code) use($item) {
            $this->emit('fail', [$code, $item, $this]);
        });
        $item->on('fail', function($code) use($item) {
            $this->emit('fail', [$code, $item, $this]);
        });
    }

    protected function timerListeners(Runnable $item)
    {
        $this->stdListeners($item);
        $item->on('trigger', function(?int $pid, float $startMicrotime) use($item) {
            $this->emit('trigger', [$pid, $startMicrotime, $item, $this]);
        });
        $item->on('done', function($code) use($item) {
            $this->emit('done', [$code, $item, $this]);
        });
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

    public function getName()
    {
        return $this->name;
    }

    public function setParent(self $parent): void
    {
        $this->parent = $parent;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    /**
     * @return Manager
     */
    public function getManager(): self
    {
        if($this->parent) {
            return $this->parent->getManager();
        }
        return $this;
    }

    public function addChild(self $group, ?int $index=null): self
    {
        $group->setParent($this);
        $this->add($this->children, $group, $index);
        $group->on('stdout', function($data, $item, $group) {
            $this->emit('stdout', [$data, $item, $group]);
        });
        $group->on('stderr', function($data, $item, $group) {
            $this->emit('stderr', [$data, $item, $group]);
        });
        $group->on('exit', function($code, $item, $group) {
            $this->emit('fail', [$code, $item, $group]);
        });
        $group->on('fail', function($code, $item, $group) {
            $this->emit('fail', [$code, $item, $group]);
        });
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
        $item = new Service($this->getManager()->getLoop(), $name, $cmd, $cwd, $env, $instanceCount, $keepAlive, $validExitCodes);
        $this->add($this->services, $item);
        $this->serviceListeners($item);
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
        $item = new Timer($this->getManager()->getLoop(), $name, $cron, $cmd, $cwd, $env);
        $this->add($this->timers, $item);
        $this->timerListeners($item);
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
    }

    public function stop(): void
    {
        foreach($this->iterate() as $runnable) {
            $runnable->stop();
        }
    }

    public function restart(): void
    {
        foreach($this->iterate() as $runnable) {
            $runnable->stop();
        }
        foreach($this->iterate() as $runnable) {
            $runnable->start();
        }
    }

    public function isRunning(): bool
    {
        foreach($this->iterate() as $item) {
            if($item->isRunning()) {
                return true;
            }
        }
        return false;
    }

    public function toArray(): array
    {
        $a = [
            'name' => $this->getName(),
            'uid' => $this->getUid(),
            'running' => $this->isRunning(),
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