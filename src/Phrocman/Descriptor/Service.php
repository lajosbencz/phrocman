<?php

namespace Phrocman\Descriptor;

use Phrocman\Descriptor;

class Service extends Descriptor
{
    protected $cnt = 1;
    protected $validExitCodes = [];

    public function __construct(string $tag, string $cmd, int $cnt=1, ?string $cwd=null, ?array $env=null, array $validExitCodes=[])
    {
        parent::__construct($tag, $cmd, $cwd, $env);
        $this->cnt = $cnt;
        $this->validExitCodes = $validExitCodes;
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

    /**
     * @return int[]
     */
    public function getValidExitCodes(): array
    {
        return $this->validExitCodes;
    }

    /**
     * @param array $validExitCodes
     * @return Service
     */
    public function setValidExitCodes(array $validExitCodes): Service
    {
        $this->validExitCodes = $validExitCodes;
        return $this;
    }

    public function isValidExitCode(?int $code): bool
    {
        if(!is_int($code)) {
            return true;
        }
        return in_array($code, $this->validExitCodes);
    }

}