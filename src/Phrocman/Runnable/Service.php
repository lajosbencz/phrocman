<?php

namespace Phrocman\Runnable;


use Phrocman\Runnable;

class Service extends Runnable
{
    protected $cnt = 1;

    public function __construct(string $tag, string $cmd, int $cnt=1, ?string $cwd=null, ?array $env=null)
    {
        parent::__construct($tag, $cmd, $cwd, $env);
        $this->cnt = $cnt;
    }

    /**
     * @return int
     */
    public function getCnt(): int
    {
        return $this->cnt;
    }

    /**
     * @param int $cnt
     * @return $this
     */
    public function setCnt(int $cnt): self
    {
        $this->cnt = $cnt;
        return $this;
    }

}
