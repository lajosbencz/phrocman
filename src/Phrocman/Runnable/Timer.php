<?php

namespace Phrocman\Runnable;


use Phrocman\Cron;
use Phrocman\Descriptor;
use Phrocman\Runnable;
use React\ChildProcess\Process;
use React\EventLoop\LoopInterface;

class Timer extends Runnable
{
    /** @var Cron */
    protected $cron;

    protected $running = false;

    public function __construct(LoopInterface $loop, string $name, Cron $cron, string $cmd, string $cwd = '', array $env = [])
    {
        parent::__construct($loop, $name, $cmd, $cwd, $env);
        $this->cron = $cron;
    }

    public function getCron(): Cron
    {
        return $this->cron;
    }

    public function tickSecond(\DateTime $dateTime): void
    {
        if ($this->isRunning() && $this->getCron()->check($dateTime)) {
            $cmd = $this->getCmd();
            $cwd = $this->getCwd();
            $env = $this->getEnv();
            $process = new Process($cmd, $cwd, $env);
            $start = microtime(true);
            $process->start($this->loop);
            $process->stdout->on('data', function ($data) {
                $this->emit('stdout', [$data]);
            });
            $process->stderr->on('data', function ($error) {
                $this->emit('stderr', [$error]);
            });
            $process->on('exit', function ($code) use($start) {
                $took = microtime(true) - $start;
                $this->emit('done', [$code, $took]);
            });
            $this->emit('trigger', [$process->getPid(), $start]);
        }
    }

    public function start(): void
    {
        $this->running = true;
    }

    public function stop(): void
    {
        $this->running = false;
    }

    public function restart(): void
    {
        $this->running = true;
    }

    public function isRunning(): bool
    {
        return $this->running;
    }

    public function toArray(): array
    {
        return [
            'timer' => $this->getUid(),
            'name' => $this->getName(),
            'cron' => $this->getCron()->toArray(),
            'cmd' => $this->getCmd(),
            'running' => $this->isRunning(),
        ];
    }

}
