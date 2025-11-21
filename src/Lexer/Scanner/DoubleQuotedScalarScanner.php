<?php

namespace MaxBeckers\YamlParser\Lexer\Scanner;

use MaxBeckers\YamlParser\Exception\LexerException;
use MaxBeckers\YamlParser\Lexer\ContextMode;
use MaxBeckers\YamlParser\Lexer\LexerContext;
use MaxBeckers\YamlParser\Lexer\Token;
use MaxBeckers\YamlParser\Lexer\TokenType;

final class DoubleQuotedScalarScanner extends AbstractScanner
{
    public static function scan(LexerContext $context, string $currentChar): bool
    {
        if ($currentChar !== '"') {
            return false;
        }
        static::checkImplicitDocumentStart($context);

        if ($context->getMode() === ContextMode::DOCUMENT_START) {
            $context->pushMode(ContextMode::BLOCK_KEY);
        }

        $charsToPossibleEnd = 0;
        do {
            $charsToPossibleEnd += $context->getNumberOfCharsTill('"', $charsToPossibleEnd + 1);
            $backslashCount = 0;
            $checkPos = $charsToPossibleEnd;

            while ($checkPos >= 0 && $context->getInputPart(1, $checkPos) === '\\') {
                $backslashCount++;
                $checkPos--;
            }

            if ($backslashCount % 2 === 0) {
                break;
            }

            $charsToPossibleEnd++;
        } while (true);
        $scalar = $context->getInputPart($charsToPossibleEnd + 2);

        $lines = preg_split('/\r\n|\r|\n/', $scalar);
        $processedLines = [];
        $includesLineBreak = false;
        foreach ($lines as $key => $line) {
            if ($key === 0) {
                $line = substr($line, 1);
                $line = rtrim($line);
            }

            if ($key > 0) {
                $line = trim($line);
                $context->increaseLine();
            }

            if ($key === count($lines) - 1) {
                $lineLength = strlen($line);
                $context->setColumn($lineLength);
                $line = substr($line, 0, -1);
            }

            if ($line !== '') {
                $processedLines[] = $line;
            } elseif ($key > 0 && $key < count($lines) - 1) {
                $processedLines[] = "\n";
                $includesLineBreak = true;
            } else {
                $processedLines[] = $line;
            }
        }
        $finalScalar = self::handleDoubleQuoteEscaping($context, implode(' ', $processedLines));

        if ($includesLineBreak) {
            $finalScalar = str_replace([" \n ", "\n ", " \n"], "\n", $finalScalar);
        }

        $context->addToken(self::createToken($context, TokenType::DOUBLE_QUOTED_SCALAR, $finalScalar, [Token::METADATA_WAS_MULTILINE_INPUT => count($lines) > 1]));

        $spacesAfter = $context->getNumberOfCharsCount(' ', $charsToPossibleEnd + 2);
        $context->increasePosition($charsToPossibleEnd + 2 + $spacesAfter);

        return true;
    }

    private static function handleDoubleQuoteEscaping(LexerContext $context, string $scalar): string
    {
        return preg_replace_callback(
            '/\\\\(?:' .
            '([\\\\\"\/0abefnrtv NL_P])|' .
            'x([0-9a-fA-F]{2})|' .
            'u([0-9a-fA-F]{4})|' .
            'U([0-9a-fA-F]{8})|' .
            '(.)' .
            ')/',
            function ($m) use ($context) {
                if (!empty($m[5])) {
                    throw new LexerException(
                        sprintf(
                            'Invalid escape sequence \'\\%s\' in line %d, column %d',
                            $m[5],
                            $context->getLine(),
                            $context->getColumn()
                        )
                    );
                }

                if (isset($m[1])) {
                    switch ($m[1]) {
                        case '\\': return "\x5C";
                        case '"':  return "\x22";
                        case 'n':  return "\x0A";
                        case 'r':  return "\x0D";
                        case 't':  return "\x09";
                        case '0':  return "\x00";
                        case 'a':  return "\x07";
                        case 'b':  return "\x08";
                        case 'e':  return "\x1B";
                        case 'f':  return "\x0C";
                        case 'v':  return "\x0B";
                        case ' ':  return "\x20";
                        case '/':  return "\x2F";
                        case 'N':  return "\xC2\x85";
                        case '_':  return "\xC2\xA0";
                        case 'L':  return "\xE2\x80\xA8";
                        case 'P':  return "\xE2\x80\xA9";
                    }
                }

                $cp = hexdec($m[2] ?: $m[3] ?: $m[4]);

                if ($cp > 0x10FFFF || ($cp >= 0xD800 && $cp <= 0xDFFF)) {
                    throw new LexerException(
                        sprintf(
                            'Invalid Unicode codepoint: U+%06X in line %d, column %d',
                            $cp,
                            $context->getLine(),
                            $context->getColumn()
                        )
                    );
                }

                if ($cp < 0x80) {
                    return chr($cp);
                }
                if ($cp < 0x800) {
                    return chr(0xC0 | $cp >> 6) . chr(0x80 | $cp & 0x3F);
                }
                if ($cp < 0x10000) {
                    return chr(0xE0 | $cp >> 12) . chr(0x80 | $cp >> 6 & 0x3F) . chr(0x80 | $cp & 0x3F);
                }

                return chr(0xF0 | $cp >> 18) . chr(0x80 | $cp >> 12 & 0x3F) . chr(0x80 | $cp >> 6 & 0x3F) . chr(0x80 | $cp & 0x3F);
            },
            $scalar
        );
    }
}
