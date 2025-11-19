<?php

namespace MaxBeckers\YamlParser\Resolver\Tag;

class VerbatimTagValidator
{
    private const GLOBAL_TAG_PATTERN = '/^[A-Za-z][A-Za-z0-9+.-]*:.+$/';

    private const LOCAL_TAG_PATTERN = '/^![-A-Za-z0-9_.~:\/?#@!\$&\'()*+;=%]+$/';

    private const VERBATIM_PATTERN = '/^!<([-A-Za-z0-9_.~:\/?#\[\]@!$&\'()*+,;=%]+)>$/';

    public static function validate(string $tag): bool
    {
        if (!preg_match(self::VERBATIM_PATTERN, $tag, $matches)) {
            return false;
        }

        $content = $matches[1];

        if (preg_match(self::GLOBAL_TAG_PATTERN, $content)) {
            return true;
        }

        if (preg_match(self::LOCAL_TAG_PATTERN, $content)) {
            return true;
        }

        return false;
    }
}
