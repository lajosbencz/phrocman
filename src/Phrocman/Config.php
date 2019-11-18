<?php

namespace Phrocman;


class Config
{
    public static function parseIni(string $filePath) : self
    {
        $arr = parse_ini_file($filePath, true, INI_SCANNER_TYPED);
        var_dump($arr);
        return new self($arr);
    }

    public function __construct(array $cfg)
    {
    }
}
