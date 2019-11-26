<?php

namespace Phrocman\Descriptor;

use Phrocman\Cron;
use Phrocman\Descriptor;

class Timer extends Descriptor
{
    protected $cron;

    public function __construct(string $tag, Cron $cron, string $cmd, ?string $cwd = null, ?array $env = null)
    {
        parent::__construct($tag, $cmd, $cwd, $env);
        $this->cron = $cron;
    }

    /**
     * @return Cron
     */
    public function getCron(): Cron
    {
        return $this->cron;
    }
}