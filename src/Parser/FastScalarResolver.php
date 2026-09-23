<?php

namespace MaxBeckers\YamlParser\Parser;

use MaxBeckers\YamlParser\Format\Version;

final class FastScalarResolver
{
    private static array $boolMap11 = ['true' => true, 'false' => false, 'yes' => true, 'no' => false, 'on' => true, 'off' => false];
    private static array $boolMap12 = ['true' => true, 'false' => false];

    public static function resolve(Version $version, ?string $value): mixed
    {
        if ($value === null || $value === '' || $value === 'null' || $value === '~') {
            if ($value === null || $value === 'null' || $value === '~') {
                return null;
            }
            if ($version === Version::VERSION_1_1) {
                return null;
            }
        }

        if (!is_string($value) || $value === '') {
            return $value;
        }

        $first = $value[0];
        if ($first !== '+' && $first !== '-' && $first !== '.' && ($first < '0' || $first > '9')) {
            if ($first === 't' || $first === 'f' || $first === 'y' || $first === 'n' || $first === 'o'
                || $first === 'T' || $first === 'F' || $first === 'Y' || $first === 'N' || $first === 'O'
            ) {
                $lower = strtolower($value);
                $boolMap = ($version === Version::VERSION_1_1) ? self::$boolMap11 : self::$boolMap12;
                if (isset($boolMap[$lower])) {
                    return $boolMap[$lower];
                }
            }
            if ($first === 'N') {
                if (strcasecmp($value, 'null') === 0) {
                    return null;
                }
            }

            return $value;
        }

        if (strcasecmp($value, '.inf') === 0 || strcasecmp($value, '+.inf') === 0 || strcasecmp($value, '-.inf') === 0
            || strcasecmp($value, '.nan') === 0 || strcasecmp($value, '+.nan') === 0 || strcasecmp($value, '-.nan') === 0
        ) {
            return self::castYamlFloat($value);
        }

        if (strlen($value) > 2 && ($value[0] === '0') && ($value[1] === 'x' || $value[1] === 'X')) {
            $hex = substr($value, 2);
            if ($hex !== '' && ctype_xdigit($hex)) {
                return base_convert($value, 16, 10);
            }

            return $value;
        }

        if (strlen($value) > 2 && ($value[0] === '0') && ($value[1] === 'o' || $value[1] === 'O')) {
            $octal = substr($value, 2);
            if ($octal !== '' && preg_match('/^[0-7]+$/', $octal) === 1) {
                return base_convert($value, 8, 10);
            }

            return $value;
        }

        if (is_numeric($value)) {
            return strpbrk($value, '.eE') !== false ? (float) $value : (int) $value;
        }

        return $value;
    }

    private static function castYamlFloat(string $value): float
    {
        $normalized = strtolower($value);

        return match (true) {
            strcasecmp($normalized, '.inf') === 0, strcasecmp($normalized, '+.inf') === 0 => INF,
            strcasecmp($normalized, '-.inf') === 0 => -INF,
            strcasecmp($normalized, '.nan') === 0, strcasecmp($normalized, '+.nan') === 0, strcasecmp($normalized, '-.nan') === 0 => NAN,
            default => (float) $normalized,
        };
    }
}
