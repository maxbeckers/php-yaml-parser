<?php

namespace MaxBeckers\YamlParser\Lexer\Scanner;

use MaxBeckers\YamlParser\Exception\LexerException;
use MaxBeckers\YamlParser\Lexer\ContextMode;
use MaxBeckers\YamlParser\Lexer\LexerContext;
use MaxBeckers\YamlParser\Lexer\Token;
use MaxBeckers\YamlParser\Lexer\TokenType;

final class DocumentScanner extends AbstractScanner
{
    public const DOCUMENT_START = '---';
    public const DOCUMENT_END = '...';

    public static function scan(LexerContext $context, string $currentChar): bool
    {
        if ($context->getMode() === ContextMode::STREAM_START && $context->hasPendingDirectives()) {
            $charsTillEol = $context->getNumberOfCharsTill("\n\r");
            $lineRemainder = $context->getInputPart($charsTillEol);
            $trimmedLine = ltrim($lineRemainder, " \t");

            if ($trimmedLine !== ''
                && !str_starts_with($trimmedLine, '#')
                && !str_starts_with($trimmedLine, self::DOCUMENT_START)
                && $currentChar !== '%'
            ) {
                throw new LexerException(
                    "A %YAML/%TAG directive must be followed by a '---' document start marker (line {$context->getLine()}, column {$context->getColumn()})"
                );
            }
        }

        if ($context->getMode() === ContextMode::STREAM_START && $currentChar !== '%' && $currentChar !== '-' && $currentChar !== '#') {
            self::setDocumentStart($context);

            return true;
        }

        if (!in_array($currentChar, ['-', '.'], true)) {
            return false;
        }

        $documentIdentifier = $context->getInputPart(3);
        if (!in_array($documentIdentifier, [self::DOCUMENT_START, self::DOCUMENT_END], true)) {
            return false;
        }
        $tokenType = $currentChar === '-' ? TokenType::DOCUMENT_START : TokenType::DOCUMENT_END;

        if ($tokenType === TokenType::DOCUMENT_START
            && $context->getMode() !== ContextMode::STREAM_START
            && $context->getTokens() !== []
        ) {
            $lastToken = $context->getLastToken();
            if ($lastToken->is(TokenType::PLAIN_SCALAR)
                && is_string($lastToken->value)
                && preg_match('/(?:^|\s)%(YAML|TAG)\b/', $lastToken->value) === 1
            ) {
                throw new LexerException(
                    "Directives require an explicit '...' document end marker before starting a new document (line {$context->getLine()}, column {$context->getColumn()})"
                );
            }
        }

        if ($tokenType === TokenType::DOCUMENT_END
            && $context->getCurrentIndent() === -1
            && $context->hasPendingDirectives()
        ) {
            throw new LexerException(
                "A %YAML/%TAG directive must be followed by a '---' document start marker (line {$context->getLine()}, column {$context->getColumn()})"
            );
        }

        if (TokenType::DOCUMENT_START === $tokenType && $context->getMode() !== ContextMode::STREAM_START) {
            self::resetMode($context);
            self::setDocumentStart($context);
        } elseif (TokenType::DOCUMENT_START === $tokenType) {
            self::setDocumentStart($context);
        } else {
            self::resetMode($context);
        }

        $position = 3;
        $spaceChars = $context->getNumberOfCharsCount(' ', $position);

        if ($tokenType === TokenType::DOCUMENT_END) {
            $charAfterDocumentEnd = $context->getInputPart(1, $position + $spaceChars);
            if (!in_array($charAfterDocumentEnd, ['', "\n", "\r", '#'], true)) {
                throw new LexerException(
                    "Document end marker '...' must not be followed by content on the same line (line {$context->getLine()}, column {$context->getColumn()})"
                );
            }
        }

        $context->increasePositionInLine($position + $spaceChars);

        return true;
    }

    public static function resetMode(LexerContext $context): void
    {
        while ($context->getMode() !== ContextMode::STREAM_START) {
            $context->popMode();
        }
        while ($context->getCurrentIndent() > -1) {
            if ($context->getCurrentIndent() > 0) {
                $context->addToken(self::createToken($context, TokenType::DEDENT));
            }
            $context->popIndent();
        }
        $context->addToken(self::createToken($context, TokenType::DOCUMENT_END, self::DOCUMENT_END));
    }

    public static function setDocumentStart(LexerContext $context): void
    {
        $context->pushMode(ContextMode::DOCUMENT_START);
        $context->pushIndent(0);
        $context->startNewDocument();
        $context->addToken(self::createToken($context, TokenType::DOCUMENT_START, self::DOCUMENT_START, [Token::METADATA_VERSION => $context->getYamlVersion()]));
    }
}
