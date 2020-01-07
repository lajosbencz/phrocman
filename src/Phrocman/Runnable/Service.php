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
                $instance->on('stdout', function($data, UidInterface $item, Group $group) {
                    $this->getManager()->emit('stdout', func_get_args());
                });
                $instance->on('stderr', function($data, UidInterface $item, Group $group) {
                    $this->getManager()->emit('stderr', func_get_args());
                });
                $instance->on('exit', function($data, UidInterface $item, Group $group) {
                    $this->getManager()->emit('exit', func_get_args());
                });
                $instance->on('fail', function($data, UidInterface $item, Group $group) {
                    $this->getManager()->emit('fail', func_get_args());
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
            if($instance->isRunning()) {
                return true;
            }
        }
        return false;
    }

    public function start(): void
    {
        foreach($this->instances as $instance) {
            $instance->start();
        }
    }

    public function stop(): void
    {
        foreach($this->instances as $instance) {
            $instance->stop();
        }
    }

    public function restart(): void
    {
        foreach($this->instances as $instance) {
            $instance->stop();
        }
        foreach($this->instances as $instance) {
            $instance->start();
        }
    }

    public function toArray(): array
    {
        $instances = [];
        foreach($this->instances as $instance) {
            $instances[] = $instance->toArray();
        }
        return [
            'uid' => $this->getUid(),
            'name' => $this->getName(),
            'cmd' => $this->getCmd(),
            'running' => $this->isRunning(),
            'keepAlive' => $this->isKeepAlive(),
            'validExitCodes' => $this->validExitCodes,
            'instances' => $instances,
        ];
    }


}
