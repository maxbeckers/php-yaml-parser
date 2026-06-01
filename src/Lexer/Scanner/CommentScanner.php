<?php

namespace MaxBeckers\YamlParser\Lexer\Scanner;

use MaxBeckers\YamlParser\Exception\LexerException;
use MaxBeckers\YamlParser\Lexer\LexerContext;
use MaxBeckers\YamlParser\Format\Version;

final class CommentScanner extends AbstractScanner
{
    public static function scan(LexerContext $context, string $currentChar): bool
    {
        if ($currentChar !== '#') {
            return false;
        }
        if (!in_array($context->getInputPart(1, -1), self::commentPreChars($context), true)) {
            throw new LexerException(sprintf('Comment character \'#\' must be preceded by whitespace at line %d, column %d', $context->getLine(), $context->getColumn()));
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
