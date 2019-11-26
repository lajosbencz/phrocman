<?php

namespace Phrocman;

class Descriptor
{
    use UidTrait;

    protected $cmd = '';
    protected $cwd = '';
    protected $env = [];
    protected $tag = null;

    public function __construct(string $tag, string $cmd, ?string $cwd=null, ?array $env=null)
    {
        $this->tag = $tag ? : null;
        $this->cmd = $cmd;
        $this->cwd = $cwd;
        $this->env = $env;
        $this->generateUid();
    }

    /**
     * @return string
     */
    public function getTag(): string
    {
        return $this->tag ?? $this->cmd;
    }

    /**
     * @return string
     */
    public function getCmd(): string
    {
        return $this->cmd;
    }

    /**
     * @return string|null
     */
    public function getCwd(): ?string
    {
        return $this->cwd;
    }

    /**
     * @param array|null $override
     * @return array|null
     */
    public function getEnv(?array $override=null): ?array
    {
        if(is_array($override)) {
            return array_merge($this->env ?? [], $override ?? []);
        }
        return $this->env;
    }
}