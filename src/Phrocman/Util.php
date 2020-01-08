<?php


namespace Phrocman;


final class Util
{
    private function __construct()
    {
    }

    static function formatSize($size)
    {
        $unit = ['b', 'kb', 'mb', 'gb', 'tb', 'pb'];
        return sprintf('%0.2f', $size / pow(1024, ($i = floor(log($size, 1024))))) . ' ' . $unit[$i];
    }
}