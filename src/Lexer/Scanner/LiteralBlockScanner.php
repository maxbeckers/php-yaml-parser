<?php

namespace MaxBeckers\YamlParser\Lexer\Scanner;

use MaxBeckers\YamlParser\Exception\LexerException;
use MaxBeckers\YamlParser\Format\FormatHelper;
use MaxBeckers\YamlParser\Lexer\LexerContext;
use MaxBeckers\YamlParser\Lexer\TokenType;

final class LiteralBlockScanner extends AbstractScanner
{
    public static function scan(LexerContext $context, string $currentChar): bool
    {
        if ($currentChar !== '|') {
            return false;
        }
        static::checkImplicitDocumentStart($context);
        $startLine = $context->getLine();
        $startColumn = $context->getColumn();
        $context->increasePosition();

        $chompingIndicator = null;
        $indentationIndicator = null;

        $nextChar = $context->getInputPart(1);
        if ($nextChar === '-' || $nextChar === '+') {
            $chompingIndicator = $nextChar;
            $context->increasePosition();
            $nextChar = $context->getInputPart(1);
        }

        if (FormatHelper::isNumeric($nextChar)) {
            $indentationIndicator = (int) $nextChar;
            $context->increasePosition(1);
            $nextChar = $context->getInputPart(1);
            if ($indentationIndicator === 0) {
                throw new LexerException(sprintf('Block scalar indentation indicator cannot be 0 at line %d, column %d', $context->getLine(), $context->getColumn()));
            }
            if (FormatHelper::isNumeric($nextChar)) {
                throw new LexerException(sprintf('Block scalar indentation indicator cannot be more than 9 at line %d, column %d', $context->getLine(), $context->getColumn()));
            }
        }

        if ($chompingIndicator === null && ($nextChar === '-' || $nextChar === '+')) {
            $chompingIndicator = $nextChar;
            $context->increasePosition();
        }

        if ($context->getInputPart(1) === '#') {
            throw new LexerException(sprintf('Comment \'#\' must be preceded by whitespace after block scalar indicator at line %d, column %d', $context->getLine(), $context->getColumn()));
        }

        $charsToNewline = $context->getNumberOfCharsTill("\n\r");
        if ($charsToNewline > 0) {
            $spacesAfterIndicator = $context->getNumberOfCharsCount(' ');
            $charAfterSpaces = $context->getInputPart(1, $spacesAfterIndicator);
            if ($spacesAfterIndicator === 0 || ($charAfterSpaces !== '#' && $charAfterSpaces !== '' && $charAfterSpaces !== "\n" && $charAfterSpaces !== "\r")) {
                throw new LexerException(sprintf('Invalid content after block scalar indicator at line %d, column %d', $context->getLine(), $context->getColumn()));
            }
        }
        $context->increasePosition($charsToNewline);

        if ($context->getInputPart(1) === "\r" && $context->getInputPart(1, 1) === "\n") {
            $context->increasePosition(2);
        } elseif ($context->getInputPart(1) === "\n" || $context->getInputPart(1) === "\r") {
            $context->increasePosition();
        }
        $context->increaseLine();

        $blockIndent = $indentationIndicator;
        $parentIndent = $context->getCurrentIndent();
        $lines = [];
        $fixedIndentOrContentSeen = $blockIndent !== null;
        $maxLeadingEmptyIndent = 0;

        while (!$context->isAtEndOfFile()) {
            $lineIndent = $context->getNumberOfCharsCount(' ');
            $charAfterIndent = $context->getInputPart(1, $lineIndent);

            if ($charAfterIndent === "\t" && $lineIndent === 0 && $blockIndent === null) {
                throw new LexerException(sprintf('Tab character found in block scalar indentation at line %d, column %d', $context->getLine(), $context->getColumn()));
            }

            if ($charAfterIndent === "\n" || $charAfterIndent === "\r") {
                $newlineSize = ($charAfterIndent === "\r" && $context->getInputPart(1, $lineIndent + 1) === "\n") ? 2 : 1;

                if (!$fixedIndentOrContentSeen && $lineIndent > $maxLeadingEmptyIndent) {
                    $maxLeadingEmptyIndent = $lineIndent;
                }

                if (!$fixedIndentOrContentSeen && ($blockIndent === null || $lineIndent > $blockIndent)) {
                    $blockIndent = $lineIndent;
                }

                $emptyLineContent = '';
                if ($blockIndent !== null && $lineIndent > $blockIndent) {
                    $emptyLineContent = str_repeat(' ', $lineIndent - $blockIndent);
                }
                $peekPos = $lineIndent + $newlineSize;
                $nextLineIndent = $context->getNumberOfCharsCount(' ', $peekPos);
                $nextCharAfterIndent = $context->getInputPart(1, $peekPos + $nextLineIndent);

                $willBreak = false;
                if ($context->isAtEndOfFile($peekPos)) {
                    $willBreak = true;
                } elseif ($blockIndent !== null && $nextCharAfterIndent !== "\n" && $nextCharAfterIndent !== "\r") {
                    if ($fixedIndentOrContentSeen && $nextLineIndent < $blockIndent && $nextCharAfterIndent !== '#') {
                        $willBreak = true;
                    } elseif ($nextLineIndent <= $parentIndent && $nextCharAfterIndent !== '#') {
                        $nextCharsToEol = $context->getNumberOfCharsTill("\n\r", $peekPos + $nextLineIndent);
                        $nextLine = $context->getInputPart($nextCharsToEol, $peekPos + $nextLineIndent);
                        if (self::isDocumentBoundary($nextLine) || preg_match('/:\s|:$|-\s|-$/', $nextLine)) {
                            $willBreak = true;
                        }
                    }
                }

                $lines[] = $emptyLineContent;

                if ($willBreak) {
                    $context->increasePosition($lineIndent);
                    break;
                }
                $context->increasePosition($lineIndent + $newlineSize);
                $context->increaseLine();
                continue;
            }

            if (!$fixedIndentOrContentSeen && $blockIndent !== null && $lineIndent < $maxLeadingEmptyIndent) {
                throw new LexerException(
                    sprintf(
                        'Literal block scalar indentation less than the defined indentation in line %d, column %d',
                        $context->getLine(),
                        $context->getColumn()
                    )
                );
            }

            if (!$fixedIndentOrContentSeen && ($blockIndent === null || $lineIndent > $blockIndent)) {
                $blockIndent = $lineIndent;
            }
            $fixedIndentOrContentSeen = true;

            if ($lineIndent <= $parentIndent && $charAfterIndent !== '#') {
                $charsToEol = $context->getNumberOfCharsTill("\n\r", $lineIndent);
                $line = $context->getInputPart($charsToEol, $lineIndent);

                if (self::isDocumentBoundary($line) || preg_match('/:\s|:$|-\s|-$/', $line)) {
                    break;
                }
            }

            if ($lineIndent < $blockIndent) {
                if ($charAfterIndent !== '#') {
                    throw new LexerException(
                        sprintf(
                            'Literal block scalar indentation less than the defined indentation in line %d, column %d',
                            $context->getLine(),
                            $context->getColumn()
                        )
                    );
                }
                break;
            }

            $contentStart = $blockIndent;
            $charsToEol = $context->getNumberOfCharsTill("\n\r", $contentStart);
            $newlineSize = 0;
            $nextNewlineChar = $context->getInputPart(1, $contentStart + $charsToEol);
            if ($nextNewlineChar === "\r" && $context->getInputPart(1, $contentStart + $charsToEol + 1) === "\n") {
                $newlineSize = 2;
            } elseif ($nextNewlineChar === "\n" || $nextNewlineChar === "\r") {
                $newlineSize = 1;
            }

            $line = $context->getInputPart($charsToEol, $contentStart);
            $lines[] = $line;

            $peekPos = $contentStart + $charsToEol + $newlineSize;
            $nextLineIndent = $context->getNumberOfCharsCount(' ', $peekPos);
            $nextCharAfterIndent = $context->getInputPart(1, $peekPos + $nextLineIndent);

            $willBreak = false;
            if ($context->isAtEndOfFile($peekPos)) {
                $willBreak = true;
            } elseif ($nextCharAfterIndent !== "\n" && $nextCharAfterIndent !== "\r") {
                if ($nextLineIndent < $blockIndent && $nextCharAfterIndent !== '#') {
                    $willBreak = true;
                } elseif ($nextLineIndent <= $parentIndent && $nextCharAfterIndent !== '#') {
                    $nextCharsToEol = $context->getNumberOfCharsTill("\n\r", $peekPos + $nextLineIndent);
                    $nextLine = $context->getInputPart($nextCharsToEol, $peekPos + $nextLineIndent);
                    if (self::isDocumentBoundary($nextLine) || preg_match('/:\s|:$|-\s|-$/', $nextLine)) {
                        $willBreak = true;
                    }
                }
            }

            if ($willBreak) {
                $context->increasePosition($contentStart + $charsToEol);
                break;
            }
            $context->increasePosition($contentStart + $charsToEol + $newlineSize);
            if ($newlineSize > 0) {
                $context->increaseLine();
            }

        }

        $scalar = implode("\n", $lines);

        if ($chompingIndicator === '-') {
            $scalar = rtrim($scalar, "\n");
        } elseif ($chompingIndicator === '+') {
            $scalar = $scalar . "\n";
        } else {
            $scalar = rtrim($scalar, "\n") . "\n";
        }

        $context->addToken(self::createToken(
            $context,
            TokenType::LITERAL_SCALAR,
            $scalar,
            self::createScalarTokenMetadata($context, true, $startLine, $startColumn)
        ));

        return true;
    }

    private static function isDocumentBoundary(string $line): bool
    {
        if (!str_starts_with($line, DocumentScanner::DOCUMENT_START)
            && !str_starts_with($line, DocumentScanner::DOCUMENT_END)
        ) {
            return false;
        }

        $charAfterMarker = $line[3] ?? '';

        return in_array($charAfterMarker, ['', ' ', "\t", '#'], true);
    }
}
