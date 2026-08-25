<?php

namespace MaxBeckers\YamlParser\Lexer\Scanner;

use MaxBeckers\YamlParser\Exception\LexerException;
use MaxBeckers\YamlParser\Lexer\LexerContext;
use MaxBeckers\YamlParser\Format\Version;

final class CommentScanner extends AbstractScanner
{
    private static array $COMMENT_PRE_CHARS_12 = ['', ' ', "\n"];
    private static array $COMMENT_PRE_CHARS_11 = ['', ' ', "\n", "\t"];

    public static function scan(LexerContext $context, string $currentChar): bool
    {
        if ($currentChar !== '#') {
            return false;
        }
        $prevChar = $context->getInputPart(1, -1);
        $allowedChars = $context->getYamlVersion() === Version::VERSION_1_1
            ? self::$COMMENT_PRE_CHARS_11
            : self::$COMMENT_PRE_CHARS_12;

        if (!in_array($prevChar, $allowedChars, true)) {
            throw new LexerException(sprintf('Comment character \'#\' must be preceded by whitespace at line %d, column %d', $context->getLine(), $context->getColumn()));
        }

        $charsTillEol = $context->getNumberOfCharsTill("\n\r");
        $context->increasePosition($charsTillEol);

        return true;
    }
}
