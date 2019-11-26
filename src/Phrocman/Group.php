<?php

namespace Phrocman;

use Evenement\EventEmitterTrait;
use Phrocman\Runnable\Service;
use Phrocman\Runnable\Timer;

class Group implements RunnableInterface, UidInterface
{
    use EventEmitterTrait, UidTrait;

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

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function getName()
    {
        return $this->name;
    }

    public function addChild(self $group, ?int $index=null): self
    {
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

    public function getChildren(): array
    {
        return $this->children;
    }

    protected function setupListeners(Runnable $item)
    {
        $item->on('stdout', function($data) use($item) {
            $this->emit('stdout', [$data, $item, $this]);
        });
        $item->on('stderr', function($data) use($item) {
            $this->emit('stderr', [$data, $item, $this]);
        });
        $item->on('exit', function($code) use($item) {
            $this->emit('fail', [$code, $item, $this]);
        });
        $item->on('fail', function($code) use($item) {
            $this->emit('fail', [$code, $item, $this]);
        });
    }

    public function addService(Service $item, ?int $index=null): self
    {
        $this->add($this->services, $item, $index);
        $this->setupListeners($item);
        return $this;
    }

    public function getServices(): array
    {
        return $this->services;
    }

    public function addTimer(Timer $item, ?int $index=null): self
    {
        $this->add($this->timers, $item, $index);
        $this->setupListeners($item);
        return $this;
    }

    public function getTimers(): array
    {
        return $this->timers;
    }

    /**
     * @return \Generator|RunnableInterface[]
     */
    protected function iterate(): \Generator
    {
        foreach(array_merge($this->services, $this->timers, $this->children) as $runnable) {
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
            $runnable->restart();
        }
    }


    public function toArray()
    {
        $a = [
            'name' => $this->getName(),
            'uid' => $this->getUid(),
            'children' => [],
            'services' => [],
            'timers' => [],
        ];
        foreach($this->children as $child) {
            $a['children'][] = $child->toArray();
        }
        foreacH($this->services as $service) {
            $a['services'][] = $service->getDescriptor()->getUid();
        }
        foreacH($this->timers as $timer) {
            $a['timers'][] = $timer->getDescriptor()->getUid();
        }
        return $a;
    }

}