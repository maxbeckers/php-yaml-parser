<?php

namespace MaxBeckers\YamlParser\Lexer\Scanner;

use MaxBeckers\YamlParser\Lexer\LexerContext;
use MaxBeckers\YamlParser\Lexer\Token;
use MaxBeckers\YamlParser\Lexer\TokenType;

abstract class AbstractScanner
{
    abstract public static function scan(LexerContext $context, string $currentChar): bool;

    protected static function checkImplicitDocumentStart(LexerContext $context): void
    {
        if ($context->getCurrentIndent() === -1) {
            DocumentScanner::setDocumentStart($context);
        }
    }

    protected static function createToken(LexerContext $context, TokenType $type, mixed $value = null, $metadata = []): Token
    {
        return new Token($type, $value, $context->getLine(), $context->getColumn(), $metadata);
    }
}
