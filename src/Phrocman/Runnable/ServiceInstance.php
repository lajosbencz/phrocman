<?php

namespace Phrocman\Runnable;


use Phrocman\Runnable;
use React\ChildProcess\Process;
use React\EventLoop\LoopInterface;

class ServiceInstance extends Runnable
{
    /** @var Service */
    protected $service;

    /** @var int */
    protected $instance;

    /** @var Process */
    protected $process;

    /** @var bool */
    protected $stopped = false;

    protected function createProcess()
    {
        $process = $this->process;
        if($process && $process->isRunning()) {
            $process->terminate(SIGKILL);
            $process->close();
        }
        $this->process = new Process($this->getCmd(), $this->getCwd(), $this->getEnv());
    }

    public function __construct(Service $service, int $instance)
    {
        parent::__construct($service->getGroup(), $service->getName(), $service->getCmd(), $service->getCwd(), $service->getEnv());
        $this->service = $service;
        $this->instance = $instance;
        $this->createProcess();
    }

    public function getService(): Service
    {
        return $this->service;
    }

    public function getProcess(): Process
    {
        return $this->process;
    }

    public function getInstance()
    {
        return $this->instance;
    }

    public function start(): void
    {
        $this->stopped = false;
        if(!$this->isRunning()) {
            $this->createProcess();
            $this->process->start($this->getLoop());
            $this->getManager()->emit('start', [$this, $this->getService()->getGroup()]);

            $this->process->stdout->on('data', function ($data) {
                $this->getManager()->emit('stdout', [$data, $this, $this->getService()->getGroup()]);
            });

            $this->process->stderr->on('data', function ($error) {
                $this->getManager()->emit('stderr', [$error, $this, $this->getService()->getGroup()]);
            });

            $this->process->on('exit', function ($code) {
                $code = $code ?? 0;
                $service = $this->getService();
                if(!$this->stopped && $service->isKeepAlive() && !$service->isValidExitCode($code)) {
                    $this->getManager()->emit('fail', [$code, $this, $this->getService()->getGroup()]);
                    $this->createProcess();
                    $this->start();
                } else {
                    $this->getManager()->emit('exit', [$code, $this, $this->getService()->getGroup()]);
                }
            });
        }
    }

    public function stop(): void
    {
        $this->stopped = true;
        if($this->isRunning()) {
            foreach ($this->process->pipes as $pipe) {
                $pipe->close();
            }
            $this->process->terminate();
        }
    }

    public function isRunning(): bool
    {
        return $this->process->isRunning();
    }

    public function toArray(): array
    {
        return [
            'uid' => $this->getUid(),
            'pid' => $this->getProcess()->getPid(),
            'running' => $this->isRunning(),
            'instance' => $this->getInstance(),
        ];
    }

}