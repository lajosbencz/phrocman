<?php

namespace Phrocman\Runnable;


use Phrocman\Cron;
use Phrocman\Descriptor;
use Phrocman\Group;
use Phrocman\Manager;
use Phrocman\Runnable;
use React\ChildProcess\Process;
use React\EventLoop\LoopInterface;

class Timer extends Runnable
{
    /** @var Cron */
    protected $cron;

    /** @var bool */
    protected $running = false;

    /** @var int */
    protected $lastRun = 0;

    public function __construct(Group $group, string $name, Cron $cron, string $cmd, string $cwd = '', array $env = [])
    {
        parent::__construct($group, $name, $cmd, $cwd, $env);
        $this->cron = $cron;
    }

    public function getCron(): Cron
    {
        return $this->cron;
    }

    public function getLastRun(): int
    {
        return $this->lastRun;
    }

    public function tickSecond(\DateTime $dateTime): void
    {
        if ($this->isRunning() && $this->getCron()->check($dateTime)) {
            $cmd = $this->getCmd();
            $cwd = $this->getCwd();
            $env = $this->getEnv();
            $this->lastRun = $start = microtime(true);
            $process = new Process($cmd, $cwd, $env);
            $process->on('exit', function ($code) use($start) {
                $took = microtime(true) - $start;
                $this->emit('exit', [$code, $took]);
            });
            $process->start($this->getLoop());
            $process->stdout->on('data', function ($data) {
                $this->emit('stdout', [$data]);
            });
            $process->stderr->on('data', function ($error) {
                $this->emit('stderr', [$error]);
            });
            $this->emit('trigger');
        }
    }

    public function start(): void
    {
        $this->running = true;
        $this->emit('start');
    }

    public function stop(): void
    {
        $this->running = false;
        $this->emit('stop');
    }

    public function restart(): void
    {
        if(!$this->running) {
            $this->running = true;
            $this->emit('start');
        }
    }

    public function isRunning(): bool
    {
        return $this->running;
    }

    public function toArray(): array
    {
        return [
            'type' => 'timer',
            'name' => $this->getName(),
            'uid' => $this->getUid(),
            'cron' => $this->getCron()->toArray(),
            'cmd' => $this->getCmd(),
            'running' => $this->isRunning(),
            'last_run' => $this->getLastRun(),
        ];
    }

}
