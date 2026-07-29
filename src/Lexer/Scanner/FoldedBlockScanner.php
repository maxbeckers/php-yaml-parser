<?php

namespace MaxBeckers\YamlParser\Lexer\Scanner;

use MaxBeckers\YamlParser\Exception\LexerException;
use MaxBeckers\YamlParser\Lexer\LexerContext;
use MaxBeckers\YamlParser\Lexer\TokenType;
use MaxBeckers\YamlParser\Format\FormatHelper;

final class FoldedBlockScanner extends AbstractScanner
{
    public static function scan(LexerContext $context, string $currentChar): bool
    {
        if ($currentChar !== '>') {
            return false;
        }
        static::checkImplicitDocumentStart($context);
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

        if ($nextChar === '-' || $nextChar === '+') {
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

        $blockIndent = $indentationIndicator;
        $wasEmptyLineIndent = false;
        $isBlockIndentFixed = $blockIndent !== null;
        $maxLeadingEmptyIndent = 0;
        $currentIndent = $context->getCurrentIndent();
        $lines = [];
        while (true) {
            if ($context->isAtEndOfFile()) {
                break;
            }
            $nextChar = $context->getInputPart(1);
            if ($nextChar === "\r" && $context->getInputPart(1, 1) === "\n") {
                $newlineChars = 2;
            } elseif ($nextChar === "\n" || $nextChar === "\r") {
                $newlineChars = 1;
            } else {
                $newlineChars = 0;
            }
            $lineStart = $lineIndent = $context->getNumberOfCharsCount(' ', $newlineChars);
            if ($isBlockIndentFixed && $lineStart > $blockIndent) {
                $lineStart = $blockIndent;
            }
            $charsToEol = $context->getNumberOfCharsTill("\n\r", $lineStart + $newlineChars);
            if ($blockIndent === null || ($lineStart > $blockIndent && $wasEmptyLineIndent)) {
                $blockIndent = $lineIndent;
                $wasEmptyLineIndent = $charsToEol === 0;
            }
            if ($charsToEol === 0) {
                if (!$isBlockIndentFixed && $lineIndent > $maxLeadingEmptyIndent) {
                    $maxLeadingEmptyIndent = $lineIndent;
                }

                if ($lineStart < $blockIndent || $wasEmptyLineIndent) {
                    $lines[] = '';
                }
                if ($newlineChars > 0) {
                    $context->increaseLine();
                }
                $context->increasePosition($lineStart + $newlineChars);
                continue;
            }

            if (!$isBlockIndentFixed && $blockIndent !== null && $lineIndent < $maxLeadingEmptyIndent) {
                throw new LexerException(sprintf('Folded block scalar indentation less than the defined indentation in line %d, column %d', $context->getLine(), $context->getColumn()));
            }

            if ($lineIndent <= $currentIndent) {
                $line = $context->getInputPart($charsToEol, $lineStart + $newlineChars);
                if (preg_match('/^[^#]*(:|-)(?:\s|$)/', $line)) {
                    break;
                }
            }
            if ($lineStart < $blockIndent && $charsToEol > 0 && '#' !== $context->getInputPart(1, $lineStart + $newlineChars)) {
                throw new LexerException(sprintf('Folded block scalar indentation less than the defined indentation in line %d, column %d', $context->getLine(), $context->getColumn()));
            }

            $charAfterIndent = $context->getInputPart(1, $blockIndent + $newlineChars);
            if ($lineIndent < $blockIndent && $charAfterIndent !== "\n" && $charAfterIndent !== "\r" && $charAfterIndent !== '') {
                break;
            }
            $wasEmptyLineIndent = false;
            $isBlockIndentFixed = true;

            if ($newlineChars > 0) {
                $context->increaseLine();
            }

            if ($lineStart > $blockIndent) {
                $lineStart = $blockIndent;
            }

            $charsToEol = $context->getNumberOfCharsTill("\n\r", $lineStart + $newlineChars);
            $line = $context->getInputPart($charsToEol, $lineStart + $newlineChars);

            $lines[] = $line;

            $context->increasePosition($lineStart + $charsToEol + $newlineChars);
        }

        $foldedParts = [];
        $buffer = '';
        foreach ($lines as $line) {
            if (trim($line) === '') {
                if ($buffer !== '') {
                    $foldedParts[] = $buffer;
                    $buffer = '';
                }
                $foldedParts[] = $line;
            } elseif (strspn($line, ' ') > 0) {
                if ($buffer !== '') {
                    $foldedParts[] = $buffer;
                    $buffer = '';
                }
                $buffer .= $line;
            } else {
                if ($buffer !== '') {
                    $buffer .= ' ';
                }
                $buffer .= $line;
            }
        }
        if ($buffer !== '') {
            $foldedParts[] = $buffer;
        }

        foreach ($foldedParts as $key => $part) {
            // if current line is empty and last line started not with a space and next line starts not with a space, remove it
            if (trim($part) === '') {
                $prevPart = $foldedParts[$key - 1] ?? null;
                $nextPart = $foldedParts[$key + 1] ?? null;
                if ($prevPart !== null && $nextPart !== null && trim($prevPart) !== '' && trim($nextPart) !== '') {
                    if (strspn($prevPart, "\t ") === 0 && strspn($nextPart, "\t ") === 0) {
                        unset($foldedParts[$key]);
                    }
                }
            }
        }

        $scalar = implode("\n", $foldedParts);

        if ($chompingIndicator === '-') {
            $scalar = rtrim($scalar, "\n");
        } elseif ($chompingIndicator === '+') {
            $scalar = $scalar . "\n";
        } else {
            $trimmed = rtrim($scalar, "\n");
            $scalar = $trimmed === '' ? '' : $trimmed . "\n";
        }

        $context->addToken(self::createToken($context, TokenType::FOLDED_SCALAR, $scalar));

        return true;
    }
}
