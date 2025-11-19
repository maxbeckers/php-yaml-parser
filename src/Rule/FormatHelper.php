<?php

namespace MaxBeckers\YamlParser\Rule;

class FormatHelper
{
    public static function matchPattern(string $pattern, string $value): bool
    {
        return preg_match('/' . $pattern . '/', $value) === 1;
    }

    public static function isNumeric(string $char): bool
    {
        if ($char === '' || $char === "\0") {
            return false;
        }

        $code = ord($char);

        return $code >= 48 && $code <= 57;    // 0-9
    }
}
