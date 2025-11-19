<?php

namespace MaxBeckers\YamlParser\Lexer\Scanner;

use MaxBeckers\YamlParser\Exception\LexerException;
use MaxBeckers\YamlParser\Lexer\LexerContext;
use MaxBeckers\YamlParser\Lexer\TokenType;

final class AnchorScanner extends AbstractScanner
{
    public static function scan(LexerContext $context, string $currentChar): bool
    {
        if ($currentChar !== '&') {
            return false;
        }

        $charsTillEoAnchor = $context->getNumberOfCharsTill("\r\n ");

        if ($charsTillEoAnchor === 0) {
            throw new LexerException(
                "Empty anchor name at line {$context->getLine()}, column {$context->getColumn()}"
            );
        }

        $anchor = $context->getInputPart($charsTillEoAnchor - 1, 1);

        $followingWhitespaces = $context->getNumberOfCharsCount(' ', $charsTillEoAnchor);
        $context->increasePosition($charsTillEoAnchor + $followingWhitespaces);

        $context->addToken(static::createToken($context, TokenType::ANCHOR, $anchor));

        return true;
    }
}
