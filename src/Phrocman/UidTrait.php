<?php

namespace Phrocman;

trait UidTrait
{
    /** @var string */
    private $uid;

    protected function generateUid(): void
    {
        $this->uid = uniqid();
    }

    public function getUid(): string
    {
        return $this->uid;
    }
}