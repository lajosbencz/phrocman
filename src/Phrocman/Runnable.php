<?php

namespace Phrocman;


use Evenement\EventEmitterTrait;
use React\EventLoop\LoopInterface;

abstract class Runnable implements RunnableInterface, UidInterface
{
    use EventEmitterTrait;

    /** @var Group */
    protected $group;

    protected $name = '';
    protected $cmd = '';
    protected $cwd = '';
    protected $env = [];
    protected $uid = '';

    public function __construct(Group $group, string $name, string $cmd, string $cwd = '', array $env = [])
    {
        $this->group = $group;
        $this->name = $name;
        $this->cmd = $cmd;
        $this->cwd = $cwd;
        $this->env = $env;
        $this->generateUid();
    }

    protected function generateUid(): void
    {
        $this->uid = md5(json_encode([get_class($this), $this->getName(), $this->getGroup()->getPath()]));
    }

    public function getUid(): string
    {
        return $this->uid;
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
