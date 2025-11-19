<?php

namespace MaxBeckers\YamlParser\Lexer\Scanner;

use MaxBeckers\YamlParser\Lexer\ContextMode;
use MaxBeckers\YamlParser\Lexer\LexerContext;
use MaxBeckers\YamlParser\Lexer\TokenType;

final class IndentationScanner extends AbstractScanner
{
    public static function scan(LexerContext $context, string $currentChar): bool
    {
        if ($currentChar !== "\n" && $currentChar !== "\r") {
            return false;
        }

        if ($currentChar === "\r") {
            $context->increasePosition();
            $currentChar = $context->getInputPart(1);
        }

        if ($currentChar === "\n") {
            $context->increasePosition();
            $context->increaseLine();
            $currentChar = $context->getInputPart(1);
        }

        if (in_array($currentChar, ["\n", "\r", '#', '%'], true) || $context->getMode() === ContextMode::STREAM_START) {
            return true;
        } elseif ($currentChar === '-' && $context->getInputPart(2, 1) === '--') {
            return true;
        }
        static::checkImplicitDocumentStart($context);

        if (in_array($context->getMode(), [ContextMode::BLOCK_VALUE, ContextMode::DOCUMENT_START], true)) {
            $nextBreakingChar = $context->getNumberOfCharsTill("\n\r#-:{}[],\"'");
            $breakingChar = $context->getInputPart(1, $nextBreakingChar);
            if (in_array($breakingChar, ["\r", "\n"], true)) {
                $spaces = $context->getNumberOfCharsCount(' ');
                $context->increasePositionInLine($spaces);

                return true;
            } elseif (in_array($breakingChar, ['"', "'", '#'], true)) {
                $endOfPart = match ($breakingChar) {
                    '#' => $context->getNumberOfCharsTill("\r\n", $nextBreakingChar + 1) + $nextBreakingChar + 1,
                    '"' => $context->getNumberOfCharsTill('"', $nextBreakingChar + 1) + $nextBreakingChar + 1,
                    "'" => $context->getNumberOfCharsTill("'", $nextBreakingChar + 1) + $nextBreakingChar + 1,
                    default => $nextBreakingChar,
                };
                $nextBreakingChar = $context->getNumberOfCharsTill("\n\r#-:{}[],", $endOfPart);
                if (in_array($context->getInputPart(1, $nextBreakingChar + $endOfPart), ["\r", "\n"], true)) {
                    $spaces = $context->getNumberOfCharsCount(' ');
                    $context->increasePositionInLine($spaces);

                    return true;
                }
            }
        } elseif ($context->getMode() === ContextMode::FLOW_SEQUENCE) {
            $nextBreakingChar = $context->getNumberOfCharsCount("\n\r ");
            $breakingChar = $context->getInputPart(1, $nextBreakingChar);
            if (in_array($breakingChar, ["\r", "\n", ']'], true)) {
                $context->increasePositionInLine($nextBreakingChar);

                return true;
            }
        } elseif ($context->getMode() === ContextMode::EXPLICIT_KEY) {
            $nextBreakingChar = $context->getNumberOfCharsTill("\n\r#-:{}[],\"'");
            $breakingChar = $context->getInputPart(1, $nextBreakingChar);
            if ($breakingChar === ':') {
                $spaces = $context->getNumberOfCharsCount(' ');
                $context->increasePositionInLine($spaces);

                return true;
            }
        }

        $spaces = $context->getNumberOfCharsCount(' ');
        $currentIndent = $context->getCurrentIndent();

        if ($spaces > 0) {
            $context->increasePositionInLine($spaces);
        }

        if ($spaces === $currentIndent) {
            return true;
        }

        if ($spaces < $currentIndent) {
            while ($context->getCurrentIndent() > $spaces) {
                $context->popIndent();
                $context->addToken(self::createToken($context, TokenType::DEDENT));
            }
        }

        if ($context->getCurrentIndent() < $spaces) {
            $context->pushIndent($spaces);
            $context->addToken(self::createToken($context, TokenType::INDENT));
        }

        return true;
    }
}
