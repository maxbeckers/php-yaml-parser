<?php

namespace MaxBeckers\YamlParser\Lexer\Scanner;

use MaxBeckers\YamlParser\Exception\LexerException;
use MaxBeckers\YamlParser\Lexer\ContextMode;
use MaxBeckers\YamlParser\Lexer\LexerContext;
use MaxBeckers\YamlParser\Lexer\TokenType;

final class CollectionScanner extends AbstractScanner
{
    public static function scan(LexerContext $context, string $currentChar): bool
    {
        if ($currentChar === '-' && in_array($context->getInputPart(1, 1), [' ', "\n", "\r"], true)) {
            self::handleHyphen($context);

            return true;
        }

        if ($currentChar === '[') {
            self::handleFlowSequenceStart($context);

            return true;
        }

        if ($currentChar === ']') {
            self::handleFlowSequenceEnd($context);

            return true;
        }

        if ($currentChar === '{') {
            self::handleFlowMappingStart($context);

            return true;
        }

        if ($currentChar === '}') {
            self::handleFlowMappingEnd($context);

            return true;
        }

        if ($currentChar === ',' && $context->isInFlow()) {
            self::handleFlowSeparator($context);

            return true;
        }

        if ($currentChar === ':') {
            if (!$context->isInFlow()
                && in_array($context->getMode(), [ContextMode::BLOCK_KEY, ContextMode::EXPLICIT_KEY, ContextMode::BLOCK_VALUE], true)
                && !in_array($context->getInputPart(1, 1), [' ', "\t", "\n", "\r", ''], true)
            ) {
                return false;
            }
            if (in_array($context->getMode(), [ContextMode::BLOCK_SEQUENCE_ENTRY, ContextMode::FLOW_SEQUENCE], true) && in_array($context->getInputPart(1, 1), [':', '/'], true)) {
                return false;
            }
            if ($context->isInFlow()
                && $context->getMode() === ContextMode::FLOW_MAPPING_VALUE
                && !in_array($context->getInputPart(1, 1), [' ', "\t", "\n", "\r", ',', ']', '}', ''], true)
            ) {
                return false;
            }
            self::handleFlowColon($context);

            return true;
        }

        if ($currentChar === '?') {
            if (in_array($context->getMode(), [ContextMode::BLOCK_KEY, ContextMode::BLOCK_VALUE, ContextMode::EXPLICIT_KEY], true)
                && !in_array($context->getInputPart(1, 1), [' ', "\t", "\n", "\r", ''], true)
            ) {
                return false;
            }
            if ($context->isInFlow() && !in_array($context->getInputPart(1, 1), [' ', "\t", "\n", "\r", ',', ']', '}', ''], true)) {
                return false;
            }
            self::handleExplicitKey($context);

            return true;
        }

        return false;
    }

    private static function handleHyphen(LexerContext $context): void
    {
        static::checkImplicitDocumentStart($context);
        $context->pushMode(ContextMode::BLOCK_SEQUENCE_ENTRY);
        $context->addToken(self::createToken($context, TokenType::SEQUENCE_INDICATOR, '-'));
        $indent = 1 + $context->getNumberOfCharsCount(' ', 1);
        $context->increasePositionInLine($indent);
        if (!in_array($context->getInputPart(1, 1), ["\n", "\r"], true)) {
            $context->pushIndent($indent + $context->getCurrentIndent());
            $context->addToken(self::createToken($context, TokenType::INDENT));
        }
    }

    private static function handleFlowSequenceStart(LexerContext $context): void
    {
        static::checkImplicitDocumentStart($context);
        $context->pushMode(ContextMode::FLOW_SEQUENCE);
        $context->addToken(self::createToken($context, TokenType::SEQUENCE_START, '['));
        $context->increasePositionInLine(1 + $context->getNumberOfCharsCount(' ', 1));
        $context->enterFlow();
    }

    private static function handleFlowSequenceEnd(LexerContext $context): void
    {
        $context->popMode();
        $context->addToken(self::createToken($context, TokenType::SEQUENCE_END, ']'));
        $context->increasePositionInLine(1 + $context->getNumberOfCharsCount(' ', 1));
        $context->exitFlow();
    }

    private static function handleFlowMappingStart(LexerContext $context): void
    {
        static::checkImplicitDocumentStart($context);
        $context->pushMode(ContextMode::FLOW_MAPPING_KEY);
        $context->addToken(self::createToken($context, TokenType::MAPPING_START, '{'));
        $context->increasePositionInLine(1 + $context->getNumberOfCharsCount(' ', 1));
        $context->enterFlow();
    }

    private static function handleFlowMappingEnd(LexerContext $context): void
    {
        $context->popMode();
        $context->addToken(self::createToken($context, TokenType::MAPPING_END, '}'));
        $context->increasePositionInLine(1 + $context->getNumberOfCharsCount(' ', 1));
        $context->exitFlow();
    }

    private static function handleFlowSeparator(LexerContext $context): void
    {
        if ($context->getMode() === ContextMode::FLOW_MAPPING_VALUE) {
            $context->popMode();
            $context->pushMode(ContextMode::FLOW_MAPPING_KEY);
        }
        if ($context->getLastToken()->is(TokenType::KEY_INDICATOR)) {
            $context->addToken(self::createToken($context, TokenType::PLAIN_SCALAR));
        }
        $context->addToken(self::createToken($context, TokenType::FLOW_SEPARATOR, ','));
        $context->increasePositionInLine(1 + $context->getNumberOfCharsCount(' ', 1));
    }

    private static function handleFlowColon(LexerContext $context): void
    {
        if ($context->getMode() === ContextMode::FLOW_MAPPING_KEY) {
            if ($context->getLastToken()->isOneOf(TokenType::MAPPING_START, TokenType::FLOW_SEPARATOR)) {
                $context->addToken(self::createToken($context, TokenType::PLAIN_SCALAR));
            }
            $context->popMode();
            $context->pushMode(ContextMode::FLOW_MAPPING_VALUE);
        } elseif ($context->getMode() === ContextMode::FLOW_SEQUENCE) {
            if ($context->getLastToken()->isOneOf(TokenType::SEQUENCE_START, TokenType::FLOW_SEPARATOR)) {
                $context->addToken(self::createToken($context, TokenType::PLAIN_SCALAR));
            }
        } elseif ($context->getMode() === ContextMode::BLOCK_KEY) {
            $context->popMode();
            $context->pushMode(ContextMode::BLOCK_VALUE);
        } elseif ($context->getMode() === ContextMode::EXPLICIT_KEY) {
            $context->popMode();
        }
        $context->addToken(self::createToken($context, TokenType::KEY_INDICATOR, ':'));
        $context->increasePositionInLine(1 + $context->getNumberOfCharsCount(" \t", 1));
    }

    private static function handleExplicitKey(LexerContext $context): void
    {
        if ($context->getInputPart(1, 1) === "\t") {
            throw new LexerException(sprintf('Tab character found in block indentation at line %d, column %d', $context->getLine(), $context->getColumn()));
        }

        $context->addToken(self::createToken($context, TokenType::EXPLICIT_KEY, '?'));
        $spaces = $context->getNumberOfCharsCount(' ', 1);
        $context->increasePositionInLine(1 + $spaces);
        $context->pushMode(ContextMode::EXPLICIT_KEY);
        $nextChar = $context->getInputPart(1);
        if ($nextChar !== '#' && $nextChar !== "\n" && $nextChar !== "\r" && $nextChar !== '') {
            $context->pushIndent(1 + $spaces);
            $context->addToken(self::createToken($context, TokenType::INDENT));
        }
    }
}
