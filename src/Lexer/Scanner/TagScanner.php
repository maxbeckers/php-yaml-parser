<?php

namespace MaxBeckers\YamlParser\Lexer\Scanner;

use MaxBeckers\YamlParser\Exception\LexerException;
use MaxBeckers\YamlParser\Lexer\ContextMode;
use MaxBeckers\YamlParser\Lexer\LexerContext;
use MaxBeckers\YamlParser\Lexer\TokenType;
use MaxBeckers\YamlParser\Resolver\Tag\VerbatimTagValidator;
use MaxBeckers\YamlParser\Rule\Document\DirectiveType;
use MaxBeckers\YamlParser\Rule\FormatHelper;
use MaxBeckers\YamlParser\Rule\Tag\TagType;

class TagScanner extends AbstractScanner
{
    public static function scan(LexerContext $context, string $currentChar): bool
    {
        if ($currentChar !== '!') {
            return false;
        }

        $charsTillEoTag = $context->getNumberOfCharsTill($context->isInFlow() ? " ,\n\r" : " \n\r");
        $tag = $context->getInputPart($charsTillEoTag);
        $tagTypes = [TagType::VERBATIM, TagType::SHORTHAND, TagType::NAMED, TagType::NON_SPECIFIC];

        foreach ($tagTypes as $tagType) {
            switch (true) {
                case $tagType === TagType::VERBATIM && preg_match('/^!<[^>]+>$/', $tag):
                    self::validateVerbatimTag($context, $tag);
                    break 2;
                case $tagType === TagType::SHORTHAND && preg_match('/^!![a-zA-Z0-9]+$/', $tag):
                case $tagType === TagType::NAMED && preg_match('/^![a-zA-Z0-9!%]+$/', $tag):
                    $tag = self::handleGlobalDirectives($context, $tag);
                    break 2;
                case $tagType === TagType::NON_SPECIFIC && $tag === '!':
                    break 2;
            }
        }
        $followingWhitespaces = $context->getNumberOfCharsCount(' ', $charsTillEoTag);
        $context->increasePosition($charsTillEoTag + $followingWhitespaces);

        $context->addToken(static::createToken($context, TokenType::TAG, $tag));

        if ($context->isInFlow() && $context->getNumberOfCharsTill(",:]}\n\r") === 0) {
            if (in_array($context->getInputPart(1), [',', ':', ']', '}'], true)) {
                $context->addToken(self::createToken($context, TokenType::PLAIN_SCALAR, ''));
            }
        } elseif (!$context->isInFlow() && $context->getMode() === ContextMode::BLOCK_SEQUENCE_ENTRY && $context->getNumberOfCharsTill("\n\r") === 0) {
            $lookAheadSpaces = $context->getNumberOfCharsCount(' ');
            $lookAheadLinebreak = $context->getNumberOfCharsCount("\n\r");
            if (in_array($context->getInputPart(1, $lookAheadSpaces + $lookAheadLinebreak), ['-', '#', ']'], true) || $context->isAtEndOfFile($lookAheadSpaces + $lookAheadLinebreak)) {
                $context->addToken(self::createToken($context, TokenType::PLAIN_SCALAR, ''));
            }
        }

        return true;
    }

    private static function validateVerbatimTag(LexerContext $context, string $tag): void
    {
        if (!VerbatimTagValidator::validate($tag)) {
            throw new LexerException(
                "Invalid verbatim tag format '{$tag}' in line {$context->getLine()}, column {$context->getColumn()}"
            );
        }
    }

    private static function handleGlobalDirectives(LexerContext $context, string $tag): string
    {
        $prefix = substr($tag, 0, strrpos($tag, '!') + 1);
        $isShorthand = FormatHelper::matchPattern('^![a-zA-Z0-9_-]+!$', $prefix);

        if ($isShorthand && substr($tag, strlen($prefix)) === '') {
            throw new LexerException(
                "Invalid tag format '{$tag}' in line {$context->getLine()}, column {$context->getColumn()}"
            );
        }

        $partialTag = null;
        $handledCorrectly = !$isShorthand;
        foreach ($context->getDirectiveTokens() as $token) {
            if ($token->value['type'] === DirectiveType::TAG) {
                $paramsParts = preg_split('/\s+/', $token->value['value']);
                if (count($paramsParts) >= 2) {
                    if ($paramsParts[0] === $prefix) {
                        $handledCorrectly = true;
                        $partialTag = '!<' . $paramsParts[1] . substr($tag, strlen($prefix)) . '>';
                        self::validateVerbatimTag($context, $partialTag);
                    }
                    if ($paramsParts[0] === $tag) {
                        $tag = '!<' . $paramsParts[1] . substr($tag, strlen($prefix)) . '>';
                        self::validateVerbatimTag($context, $tag);

                        return $tag;
                    }
                }
            }
        }

        if (!$handledCorrectly) {
            throw new LexerException(
                "Undefined tag handle '{$prefix}' in line {$context->getLine()}, column {$context->getColumn()}"
            );
        }

        return $partialTag === null ? $tag : $partialTag;
    }
}
