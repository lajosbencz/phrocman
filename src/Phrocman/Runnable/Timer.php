<?php

namespace Phrocman\Runnable;


use Phrocman\Cron;
use Phrocman\Runnable;

class Timer extends Runnable
{
    protected $cron;

    public function __construct(Cron $cron, string $cmd, ?string $cwd = null, ?array $env = null)
    {
        parent::__construct($cmd, $cwd, $env);
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
