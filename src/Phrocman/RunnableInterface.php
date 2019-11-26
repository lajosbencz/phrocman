<?php

namespace Phrocman;

interface RunnableInterface
{
    public function start(): void;
    public function stop(): void;
    public function restart(): void;
}