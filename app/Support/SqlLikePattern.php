<?php

namespace App\Support;

class SqlLikePattern
{
    public static function contains(string $value): string
    {
        return '%'.addcslashes($value, '\\%_').'%';
    }
}
