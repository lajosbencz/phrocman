<?php

namespace Phrocman\Runnable;


use Phrocman\Runnable;

class Service extends Runnable
{
    protected $cnt = 1;

    public function __construct(string $cmd, int $cnt=1, ?string $cwd=null, ?array $env=null)
    {
        parent::__construct($cmd, $cwd, $env);
        $this->cnt = $cnt;
    }

    /**
     * @return int
     */
    public function getCnt(): int
    {
        return $this->cnt;
    }

}
