<?php

namespace Phrocman;


use Evenement\EventEmitterTrait;
use React\EventLoop\LoopInterface;

abstract class Runnable implements RunnableInterface, UidInterface
{
    use UidTrait, EventEmitterTrait;

    /** @var LoopInterface */
    protected $loop;

    /** @var Descriptor */
    protected $descriptor;

    /** @var int */
    protected $instance;

    public function __construct(Descriptor $descriptor, int $instance, LoopInterface $loop)
    {
        $this->descriptor = $descriptor;
        $this->instance = $instance;
        $this->loop = $loop;
        $this->generateUid();
    }

    public function getLoop(): LoopInterface
    {
        return $this->loop;
    }

    public function getDescriptor(): Descriptor
    {
        return $this->descriptor;
    }

    public function setInstance(int $instance): self
    {
        $this->instance = $instance;
        return $this;
    }

    public function getInstance(): int
    {
        return $this->instance;
    }
}