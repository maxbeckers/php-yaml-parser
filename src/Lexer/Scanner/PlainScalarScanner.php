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
        if ('@' === $currentChar || '`' === $currentChar) {
            throw new LexerException(sprintf('Cannot start plain scalar with \'%s\': Reserved indicator in line %d, column %d', $currentChar, $context->getLine(), $context->getColumn()));
        }
        static::checkImplicitDocumentStart($context);

        if ($context->getMode() === ContextMode::DOCUMENT_START) {
            $context->pushMode(ContextMode::BLOCK_KEY);
        }

        $charsToPossibleEnd = 0;
        do {
            $lastLineEnd = $charsToPossibleEnd;
            $charsToPossibleEnd += $context->getNumberOfCharsTill("\n\r#-:{}[],.", $charsToPossibleEnd);
            $currentLineLength = $charsToPossibleEnd - $lastLineEnd;
            $actualChar = $context->getInputPart(1, $charsToPossibleEnd);
            if ($actualChar === "\n" || $actualChar === "\r") {
                do {
                    $lookAheadLine = 1;
                    if ($actualChar === "\r" && $context->getInputPart(1, $charsToPossibleEnd + 1) === "\n") {
                        $lookAheadLine++;
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
                        $charsToPossibleEnd += $lookAheadLine + $charsToPossibleEndLineAhead;
                    } elseif ($actualCharLineAhead === ':' && $context->isInFlow() && $context->getLastToken()->isOneOf(TokenType::KEY_INDICATOR, TokenType::INDENT)) {
                        $charsToPossibleEnd += $lookAheadLine + $charsToPossibleEndLineAhead;
                        break 2;
                    } elseif ($actualCharLineAhead === '-' && !in_array($context->getInputPart(1, $charsToPossibleEnd + $lookAheadLine + $charsToPossibleEndLineAhead + 1), [' ', "\n", "\r", '-'], true)) {
                        $charsToPossibleEnd += $lookAheadLine + $charsToPossibleEndLineAhead;
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
                if ($currentLineLength > 0 && $context->getNumberOfCharsCount(' ', $lastLineEnd) === $currentLineLength) {
                    break;
                }
                $charsToPossibleEnd++;
            } elseif ($actualChar === ':') {
                $nextChar = $context->getInputPart(1, $charsToPossibleEnd + 1);
                if (in_array($nextChar, [' ', "\n", "\r"], true)) {
                    break;
                } elseif ($context->isInFlow() && $nextChar === ',') {
                    break;
                }
                $charsToPossibleEnd++;
            } elseif ($actualChar === '.') {
                if ($context->getInputPart(2, $charsToPossibleEnd + 1) === '..') {
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
}
