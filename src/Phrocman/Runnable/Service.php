<?php

namespace Phrocman\Runnable;


use Phrocman\Descriptor;
use Phrocman\Runnable;
use React\ChildProcess\Process;
use React\EventLoop\LoopInterface;

class Service extends Runnable
{
    /** @var Process */
    protected $process;

    protected function createProcess()
    {
        $descriptor = $this->getDescriptor();
        $process = $this->process;
        if($process && $process->isRunning()) {
            $process->terminate(SIGKILL);
            $process->close();
        }
        $this->process = new Process($descriptor->getCmd(), $descriptor->getCwd(), $descriptor->getEnv());
    }

    public function __construct(Descriptor\Service $descriptor, int $instance, LoopInterface $loop)
    {
        parent::__construct($descriptor, $instance, $loop);
        $this->createProcess();
    }

    /**
     * @return Descriptor\Service
     */
    public function getDescriptor(): Descriptor
    {
        return parent::getDescriptor();
    }

    public function getProcess(): Process
    {
        return $this->process;
    }

    public function isRunning(): bool
    {
        return $this->process->isRunning();
    }

    public function start(): void
    {
        $this->process->start($this->loop);

        $this->process->stdout->on('data', function ($data) {
            $this->emit('stdout', func_get_args());
        });

        $this->process->stderr->on('data', function ($error) {
            $this->emit('stderr', func_get_args());
        });

        $this->process->on('exit', function ($code) {
            if(!$this->getDescriptor()->isValidExitCode($code)) {
                $this->emit('fail', func_get_args());
                $this->createProcess();
                $this->start();
            } else {
                $this->emit('exit', func_get_args());
            }
        });
    }

    public function stop(): void
    {
        foreach ($this->process->pipes as $pipe) {
            $pipe->close();
        }
        $this->process->terminate();
    }

    public function restart(): void
    {
        if($this->isRunning()) {
            $this->stop();
        }
        $this->start();
    }
}
