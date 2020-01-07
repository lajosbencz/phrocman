<?php

namespace Phrocman;


use Evenement\EventEmitterTrait;
use React\EventLoop\LoopInterface;

abstract class Runnable implements RunnableInterface, UidInterface
{
    use UidTrait, EventEmitterTrait;

    /** @var Group */
    protected $group;

    protected $name = '';
    protected $cmd = '';
    protected $cwd = '';
    protected $env = [];

    public function __construct(Group $group, string $name, string $cmd, string $cwd='', array $env=[])
    {
        $this->generateUid();
        $this->group = $group;
        $this->name = $name;
        $this->cmd = $cmd;
        $this->cwd = $cwd;
        $this->env = $env;
    }

    public function getGroup()
    {
        return $this->group;
    }

    public function getManager(): Manager
    {
        return $this->getGroup()->getManager();
    }

    public function getLoop(): LoopInterface
    {
        return $this->getManager()->getLoop();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCmd(): string
    {
        return $this->cmd;
    }

    public function getCwd(): string
    {
        return $this->cwd;
    }

    public function getEnv(): array
    {
        return $this->env;
    }

    public function restart(): void
    {
        $this->stop();
        $this->start();
    }

}
