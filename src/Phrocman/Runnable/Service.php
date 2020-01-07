<?php

namespace Phrocman\Runnable;


use Phrocman\Descriptor;
use Phrocman\Group;
use Phrocman\Manager;
use Phrocman\Runnable;
use Phrocman\UidInterface;
use React\ChildProcess\Process;
use React\EventLoop\LoopInterface;

class Service extends Runnable
{
    /** @var int */
    protected $instanceCount = 1;

    /** @var int[] */
    protected $validExitCodes = [];

    /** @var bool */
    protected $keepAlive = true;

    /** @var bool */
    protected $stopped = false;

    /** @var ServiceInstance[] */
    protected $instances = [];

    public function __construct(Group $group, string $name, string $cmd, string $cwd = '', array $env = [], int $instanceCount = 1, bool $keepAlive=true, array $validExitCodes=[])
    {
        parent::__construct($group, $name, $cmd, $cwd, $env);
        $this->keepAlive = $keepAlive;
        $this->validExitCodes = $validExitCodes;
        $this->setInstanceCount($instanceCount);
    }

    public function setInstanceCount(int $count)
    {
        $this->instanceCount = max(1, $count);
        $c = count($this->instances);
        $d = $count - $c;
        if($d > 0) {
            for($i=$c; $i<$count; $i++) {
                $instance = new ServiceInstance($this, $i);
                $instance->on('start', function() use($instance) {
                    $this->emit('start', [$instance]);
                });
                $instance->on('stdout', function($data) use($instance) {
                    $this->emit('stdout', [$instance, $data]);
                });
                $instance->on('stderr', function($data) use($instance) {
                    $this->emit('stderr', [$instance, $data]);
                });
                $instance->on('fail', function($code) use($instance) {
                    $this->emit('fail', [$instance, $code]);
                });
                $instance->on('exit', function($code) use($instance) {
                    $this->emit('exit', [$instance, $code]);
                });
                $this->instances[] = $instance;
            }
        } elseif($d < 0) {
            do {
                /** @var ServiceInstance $instance */
                $instance = array_pop($this->instances);
                $instance->stop();
                $c--;
            } while($c > 0 && $c > $count);
        }
    }

    /**
     * @return \Generator|ServiceInstance[]
     */
    public function getInstances(): \Generator
    {
        foreach($this->instances as $instance) {
            yield $instance;
        }
    }

    public function getInstanceCount(): int
    {
        return $this->instanceCount;
    }

    public function setKeepAlive(bool $keepAlive=true)
    {
        $this->keepAlive = $keepAlive;
    }

    public function isKeepAlive(): bool
    {
        return $this->keepAlive;
    }

    public function isValidExitCode(?int $code=null): bool
    {
        if($code === null) {
            return false;
        }
        return in_array($code, $this->validExitCodes);
    }

    public function isRunning(): bool
    {
        foreach($this->instances as $instance) {
            if(!$instance->isRunning()) {
                return false;
            }
        }
        return true;
    }

    public function isStopped(): bool
    {
        return $this->stopped;
    }

    public function start(): void
    {
        $this->stopped = false;
        foreach($this->instances as $instance) {
            $instance->start();
        }
        $this->emit('start', [$this]);
    }

    public function stop(): void
    {
        $this->stopped = true;
        foreach($this->instances as $instance) {
            $instance->stop();
        }
        $this->emit('stop', [$this]);
    }

    public function restart(): void
    {
        $this->stopped = false;
        foreach($this->instances as $instance) {
            $instance->stop();
        }
        $this->emit('stop', [$this]);
        foreach($this->instances as $instance) {
            $instance->start();
        }
        $this->emit('start', [$this]);
    }

    public function toArray(): array
    {
        $instances = [];
        foreach($this->instances as $instance) {
            $instances[] = $instance->toArray();
        }
        return [
            'type' => 'service',
            'name' => $this->getName(),
            'uid' => $this->getUid(),
            'cmd' => $this->getCmd(),
            'running' => $this->isRunning(),
            'keepAlive' => $this->isKeepAlive(),
            'validExitCodes' => $this->validExitCodes,
            'instances' => $instances,
        ];
    }


}
