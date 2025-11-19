<?php

namespace MaxBeckers\YamlParser\Lexer\Scanner;

use MaxBeckers\YamlParser\Lexer\LexerContext;
use MaxBeckers\YamlParser\Rule\Version;

final class CommentScanner extends AbstractScanner
{
    public static function scan(LexerContext $context, string $currentChar): bool
    {
        if ($currentChar !== '#' || !in_array($context->getInputPart(1, -1), self::commentPreChars($context), true)) {
            return false;
        }

        $charsTillEol = $context->getNumberOfCharsTill("\n\r");
        $context->increasePosition($charsTillEol);

        return true;
    }

    private static function commentPreChars(LexerContext $context): array
    {
        $allowedChars = ['', ' ', "\n"];
        if ($context->getYamlVersion() === Version::VERSION_1_1) {
            $allowedChars[] = "\t";
        }

        return $allowedChars;
    }
}
