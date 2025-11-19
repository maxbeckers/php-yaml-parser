<?php

namespace MaxBeckers\YamlParser\Lexer\Scanner;

use MaxBeckers\YamlParser\Exception\LexerException;
use MaxBeckers\YamlParser\Lexer\LexerContext;
use MaxBeckers\YamlParser\Lexer\TokenType;

final class AliasScanner extends AbstractScanner
{
    public static function scan(LexerContext $context, string $currentChar): bool
    {
        if ($currentChar !== '*') {
            return false;
        }

        $charsTillEoAlias = $context->getNumberOfCharsTill($context->isInFlow() ? "\n\r ," : "\n\r ");

        if ($charsTillEoAlias === 0) {
            throw new LexerException(
                "Empty alias name at line {$context->getLine()}, column {$context->getColumn()}"
            );
        }

        $alias = $context->getInputPart($charsTillEoAlias - 1, 1);

        $followingWhitespaces = $context->getNumberOfCharsCount(' ', $charsTillEoAlias);
        $context->increasePosition($charsTillEoAlias + $followingWhitespaces);

        $context->addToken(static::createToken($context, TokenType::ALIAS, $alias));

        return true;
    }
}
