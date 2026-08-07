<?php

namespace MaxBeckers\YamlParser\Lexer\Scanner;

use MaxBeckers\YamlParser\Exception\LexerException;
use MaxBeckers\YamlParser\Lexer\ContextMode;
use MaxBeckers\YamlParser\Lexer\LexerContext;
use MaxBeckers\YamlParser\Lexer\Token;
use MaxBeckers\YamlParser\Lexer\TokenType;

final class PlainScalarScanner extends AbstractScanner
{
    public static function scan(LexerContext $context, string $currentChar): bool
    {
        if ($currentChar === "\t") {
            if ($context->getMode() === ContextMode::BLOCK_SEQUENCE_ENTRY) {
                $nextChar = $context->getInputPart(1, 1);
                $afterNextChar = $context->getInputPart(1, 2);
                if ($nextChar === '-'
                    && in_array($afterNextChar, ['', ' ', "\t", "\n", "\r", '#'], true)
                ) {
                    throw new LexerException(sprintf('Tab character found in block indentation at line %d, column %d', $context->getLine(), $context->getColumn()));
                }

                $tabAndSpaceCount = 1 + $context->getNumberOfCharsCount(" \t", 1);
                $context->increasePositionInLine($tabAndSpaceCount);

                return true;
            }

            $nextChar = $context->getInputPart(1, 1);
            if (!$context->isInFlow()
                && in_array($context->getMode(), [ContextMode::STREAM_START, ContextMode::DOCUMENT_START, ContextMode::BLOCK_KEY, ContextMode::BLOCK_VALUE], true)
                && in_array($nextChar, ['[', '{', ']', '}', '#', "\n", "\r", ''], true)
            ) {
                $context->increasePositionInLine();

                return true;
            }
        }

        if ('@' === $currentChar || '`' === $currentChar) {
            throw new LexerException(sprintf('Cannot start plain scalar with \'%s\': Reserved indicator in line %d, column %d', $currentChar, $context->getLine(), $context->getColumn()));
        }
        static::checkImplicitDocumentStart($context);

        if ($context->getMode() === ContextMode::DOCUMENT_START) {
            $context->pushMode(ContextMode::BLOCK_KEY);
        }

        $startedAfterKeyIndicator = $context->getLastToken()->is(TokenType::KEY_INDICATOR);

        $charsToPossibleEnd = 0;
        $isMultilineInput = false;
        do {
            $lastLineEnd = $charsToPossibleEnd;
            $charsToPossibleEnd += $context->getNumberOfCharsTill("\n\r#-:{}[],.", $charsToPossibleEnd);
            $currentLineLength = $charsToPossibleEnd - $lastLineEnd;
            $actualChar = $context->getInputPart(1, $charsToPossibleEnd);
            if ($actualChar === "\n" || $actualChar === "\r") {
                $isMultilineInput = true;
                do {
                    $lookAheadLine = 1;
                    if ($actualChar === "\r" && $context->getInputPart(1, $charsToPossibleEnd + 1) === "\n") {
                        $lookAheadLine++;
                    }
                    $lineAheadOffset = $charsToPossibleEnd + $lookAheadLine;
                    $lineAheadIndent = $context->getNumberOfCharsCount(" \t", $lineAheadOffset);
                    $lineAheadFirstChar = $context->getInputPart(1, $lineAheadOffset + $lineAheadIndent);

                    if (!$context->isInFlow()) {
                        if ($context->getMode() === ContextMode::BLOCK_VALUE
                            && $lineAheadFirstChar !== "\n"
                            && $lineAheadFirstChar !== "\r"
                            && $lineAheadFirstChar !== ''
                            && $lineAheadFirstChar !== '#'
                            && $lineAheadIndent <= $context->getCurrentIndent()
                        ) {
                            break 2;
                        }

                        if ($context->getMode() === ContextMode::BLOCK_SEQUENCE_ENTRY
                            && in_array($lineAheadFirstChar, ['&', '!'], true)
                            && $lineAheadIndent <= $context->getCurrentIndent()
                        ) {
                            break 2;
                        }

                        if ($context->getMode() === ContextMode::BLOCK_SEQUENCE_ENTRY
                            && $lineAheadIndent < $context->getCurrentIndent()
                            && in_array($context->getInputPart(1, $charsToPossibleEnd), ["\n", "\r"], true)
                            && !in_array($lineAheadFirstChar, ['', "\n", "\r", '#', '-'], true)
                        ) {
                            break 2;
                        }

                        if (self::isLikelyMappingEntry($context, $lineAheadOffset, $lineAheadIndent, true)) {

                            break 2;
                        }
                    }

                    $charsToPossibleEndLineAhead = $context->getNumberOfCharsTill("\n\r-:{}[],.?", $charsToPossibleEnd + $lookAheadLine);
                    $actualCharLineAhead = $context->getInputPart(1, $charsToPossibleEnd + $lookAheadLine + $charsToPossibleEndLineAhead);

                    if ($actualCharLineAhead === "\n" || $actualCharLineAhead === "\r") {
                        $charsToPossibleEnd += $lookAheadLine + $charsToPossibleEndLineAhead;
                        $actualChar = $actualCharLineAhead;
                    } elseif ($actualCharLineAhead === '.' && !$context->isInFlow() && $context->getInputPart(2, $charsToPossibleEnd + $lookAheadLine + $charsToPossibleEndLineAhead + 1) === '..') {
                        $charsToPossibleEnd += $lookAheadLine + $charsToPossibleEndLineAhead;
                        break 2;
                    } elseif ($actualCharLineAhead === ',' && $context->isInFlow()) {
                        $charsToPossibleEnd += $lookAheadLine + $charsToPossibleEndLineAhead;
                        break 2;
                    } elseif ($actualCharLineAhead === ',' && !$context->isInFlow()) {
                        $charsToPossibleEnd += $lookAheadLine + $charsToPossibleEndLineAhead;
                    } elseif ($actualCharLineAhead === '.') {
                        $charsToPossibleEnd += $lookAheadLine + $charsToPossibleEndLineAhead + 1;
                    } elseif ($actualCharLineAhead === ':' && $context->isInFlow() && $context->getMode() === ContextMode::FLOW_MAPPING_KEY) {
                        $charsToPossibleEnd += $lookAheadLine + $charsToPossibleEndLineAhead;
                        break 2;
                    } elseif ($actualCharLineAhead === '-'
                        && !$context->isInFlow()
                        && $context->getMode() === ContextMode::BLOCK_SEQUENCE_ENTRY
                        && !$context->getLastToken()->isOneOf(TokenType::TAG, TokenType::ANCHOR)
                        && !$startedAfterKeyIndicator
                        && $context->getInputPart(1, $charsToPossibleEnd + $lookAheadLine + $charsToPossibleEndLineAhead + 1) === ' '
                        && $lineAheadIndent > 0
                        && ($lineAheadIndent + 1) === $context->getCurrentIndent()
                        && !in_array(
                            $context->getInputPart(
                                1,
                                $charsToPossibleEnd + $lookAheadLine + $charsToPossibleEndLineAhead + 2
                                + $context->getNumberOfCharsCount(' \t', $charsToPossibleEnd + $lookAheadLine + $charsToPossibleEndLineAhead + 2)
                            ),
                            ['-', '?', ':'],
                            true
                        )
                    ) {
                        $charsToPossibleEnd += $lookAheadLine + $charsToPossibleEndLineAhead + 1;
                    } elseif ($actualCharLineAhead === '-' && !in_array($context->getInputPart(1, $charsToPossibleEnd + $lookAheadLine + $charsToPossibleEndLineAhead + 1), [' ', "\n", "\r", '-'], true)) {
                        $lineAheadOffset = $charsToPossibleEnd + $lookAheadLine;
                        $lineAheadIndent = $context->getNumberOfCharsCount(" \t", $lineAheadOffset);
                        if (self::isLikelyMappingEntry($context, $lineAheadOffset, $lineAheadIndent)) {
                            break 2;
                        }

                        $charsToPossibleEnd += $lookAheadLine + $charsToPossibleEndLineAhead + 1;
                    } elseif ($context->isAtEndOfFile($charsToPossibleEnd + $lookAheadLine + $charsToPossibleEndLineAhead)) {
                        $charsToPossibleEnd += $lookAheadLine + $charsToPossibleEndLineAhead;
                        break 2;
                    } else {
                        break 2;
                    }
                } while (true);
            } elseif ($actualChar === ',') {
                if ($context->isInFlow()) {
                    break;
                }
                $charsToPossibleEnd++;
            } elseif ($actualChar === '-') {
                $previousChar = $charsToPossibleEnd > 0 ? $context->getInputPart(1, $charsToPossibleEnd - 1) : '';
                if ($currentLineLength > 0
                    && $context->getNumberOfCharsCount(' ', $lastLineEnd) === $currentLineLength
                    && in_array($previousChar, ['', ' ', "\t", "\n", "\r"], true)
                ) {
                    break;
                }
                $charsToPossibleEnd++;
            } elseif ($actualChar === ':') {
                $nextChar = $context->getInputPart(1, $charsToPossibleEnd + 1);
                if (in_array($nextChar, [' ', "\t", "\n", "\r"], true)) {
                    if (!$context->isInFlow() && $context->getMode() === ContextMode::BLOCK_VALUE && $charsToPossibleEnd > 0 && $isMultilineInput) {
                        throw new LexerException(sprintf('Invalid mapping indicator in plain scalar at line %d, column %d', $context->getLine(), $context->getColumn()));
                    }
                    break;
                } elseif (!$context->isInFlow() && $context->getMode() === ContextMode::BLOCK_VALUE && $nextChar === '#' && $charsToPossibleEnd > 0 && $isMultilineInput) {
                    throw new LexerException(sprintf('Invalid mapping indicator in plain scalar at line %d, column %d', $context->getLine(), $context->getColumn()));
                } elseif ($context->isInFlow() && $nextChar === ',') {
                    break;
                }
                $charsToPossibleEnd++;
            } elseif ($actualChar === '.') {
                if ($charsToPossibleEnd === 0 && $context->getInputPart(2, $charsToPossibleEnd + 1) === '..') {
                    break;
                }
                $charsToPossibleEnd++;
            } elseif ($actualChar === '#') {
                if ($context->getInputPart(1, $charsToPossibleEnd - 1) === ' ') {
                    $charsToPossibleEnd += $context->getNumberOfCharsTill("\n\r", $charsToPossibleEnd);
                } else {
                    $charsToPossibleEnd++;
                }
            } elseif ($actualChar === '{' || $actualChar === '[') {
                if ($charsToPossibleEnd === 0 || $context->isInFlow()) {
                    break;
                }
                $charsToPossibleEnd++;
            } elseif ($actualChar === '}' || $actualChar === ']') {
                if ($context->isInFlow()) {
                    break;
                }
                $charsToPossibleEnd++;
            } else {
                break;
            }
        } while (true);
        if ($charsToPossibleEnd === 0) {
            // todo handle empty mapping key or value case
            return false;
        }

        $scalar = $context->getInputPart($charsToPossibleEnd);

        $lines = preg_split('/\r\n|\r|\n/', $scalar);

        $commentSeen = false;
        $blankSeenBeforeComment = false;
        $inlineStart = trim($lines[0] ?? '') !== '';
        $backOffset = -1;
        $prevChar = $context->getInputPart(1, $backOffset);
        while (in_array($prevChar, [' ', "\t"], true)) {
            $backOffset--;
            $prevChar = $context->getInputPart(1, $backOffset);
        }

        $startsOnFollowingLine = in_array($prevChar, ["\n", "\r"], true);
        $strictCommentContext = $context->getLastToken()->isOneOf(TokenType::KEY_INDICATOR, TokenType::DOCUMENT_START)
            && !$startsOnFollowingLine;
        foreach ($lines as $index => $rawLine) {
            if (!$commentSeen && trim($rawLine) === '' && $index > 0) {
                $blankSeenBeforeComment = true;
            }

            $commentPos = strpos($rawLine, '#');
            $isCommentStart = $commentPos !== false
                && ($commentPos === 0 || in_array($rawLine[$commentPos - 1], [' ', "\t"], true));

            if ($isCommentStart) {
                if ($strictCommentContext && $inlineStart && !$blankSeenBeforeComment) {
                    $commentSeen = true;
                }
                continue;
            }

            if ($commentSeen && trim($rawLine) !== '') {
                throw new LexerException(sprintf('Invalid comment placement in plain scalar at line %d, column %d', $context->getLine() + $index, 0));
            }
        }

        $processedLines = [];
        $includesLineBreak = false;
        foreach ($lines as $key => $line) {
            if ($key > 0) {
                $context->increaseLine();
            }

            if ($key === count($lines) - 1) {
                $lineLength = strlen($line);
                $context->setColumn($lineLength);
            }

            $commentPos = strcspn($line, '#');
            if ($commentPos !== strlen($line) && ($commentPos === 0 || $line[$commentPos - 1] === ' ')) {
                $line = substr($line, 0, $commentPos);
            }
            $line = trim($line);
            if ($line !== '') {
                $processedLines[] = $line;
            } else {
                $includesLineBreak = true;
                $processedLines[] = "\n";
            }
        }

        $finalScalar = implode(' ', $processedLines);
        if ($includesLineBreak) {
            $finalScalar = str_replace([" \n ", "\n ", " \n"], "\n", $finalScalar);
        }

        if (trim($finalScalar) === '' && $context->getMode() === ContextMode::BLOCK_KEY && !$context->isInFlow() && $context->getCurrentIndent() === 0) {
            $context->increasePositionInLine($charsToPossibleEnd + $context->getNumberOfCharsCount(' ', $charsToPossibleEnd));

            return true;
        }

        $finalScalar = rtrim($finalScalar);
        if ('' === $finalScalar && $context->getLastToken()->isScalar()) {
            $context->increasePositionInLine($charsToPossibleEnd + $context->getNumberOfCharsCount(' ', $charsToPossibleEnd));

            return true;
        }
        $context->addToken(self::createToken($context, TokenType::PLAIN_SCALAR, $finalScalar, [Token::METADATA_WAS_MULTILINE_INPUT => count($lines) > 1]));
        $context->increasePositionInLine($charsToPossibleEnd + $context->getNumberOfCharsCount(' ', $charsToPossibleEnd));

        return true;
    }

    private static function isLikelyMappingEntry(LexerContext $context, int $lineOffset, int $lineIndent, bool $allowIndented = false): bool
    {
        if ($context->isInFlow() || (!$allowIndented && $lineIndent > $context->getCurrentIndent())) {
            return false;
        }

        $lineLength = $context->getNumberOfCharsTill("\n\r", $lineOffset);
        if ($lineLength <= 0) {
            return false;
        }

        $line = ltrim($context->getInputPart($lineLength, $lineOffset), " \t");
        if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, '-')) {
            return false;
        }

        $colonPos = strpos($line, ':');
        if ($colonPos === false) {
            return false;
        }

        $nextChar = $line[$colonPos + 1] ?? '';

        return $nextChar === '' || $nextChar === ' ' || $nextChar === "\t";
    }
}
