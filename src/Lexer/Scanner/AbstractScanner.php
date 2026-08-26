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

    protected static function createToken(
        LexerContext $context,
        TokenType $type,
        mixed $value = null,
        ?array $metadata = null,
        ?int $line = null,
        ?int $column = null
    ): Token {
        return new Token(
            $type,
            $value,
            $line ?? $context->getLine(),
            $column ?? $context->getColumn(),
            $metadata
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    protected static function createScalarTokenMetadata(
        LexerContext $context,
        bool $wasMultilineInput,
        int $startLine,
        int $startColumn
    ): ?array {
        $metadata = [];
        if ($wasMultilineInput) {
            $metadata[Token::METADATA_WAS_MULTILINE_INPUT] = true;
        }

        if ($context->shouldTrackTokenStartPositions()) {
            $metadata[Token::METADATA_START_LINE] = $startLine;
            $metadata[Token::METADATA_START_COLUMN] = $startColumn;
        }

        return $metadata === [] ? null : $metadata;
    }
}
