<?php

namespace MaxBeckers\YamlParser\Lexer\Scanner;

use MaxBeckers\YamlParser\Lexer\ContextMode;
use MaxBeckers\YamlParser\Lexer\LexerContext;
use MaxBeckers\YamlParser\Lexer\Token;
use MaxBeckers\YamlParser\Lexer\TokenType;

final class SingleQuotedScalarScanner extends AbstractScanner
{
    public static function scan(LexerContext $context, string $currentChar): bool
    {
        if ($currentChar !== "'") {
            return false;
        }
        static::checkImplicitDocumentStart($context);

        if ($context->getMode() === ContextMode::DOCUMENT_START) {
            $context->pushMode(ContextMode::BLOCK_KEY);
        }

        $charsToPossibleEnd = 0;
        do {
            $charsToPossibleEnd += $context->getNumberOfCharsTill("'", $charsToPossibleEnd + 1);
            if ("'" !== $context->getInputPart(1, $charsToPossibleEnd + 2)) {
                break;
            }
            $charsToPossibleEnd += 2;
        } while (true);

        $scalar = $context->getInputPart($charsToPossibleEnd + 2);
        $lines = preg_split('/\r\n|\r|\n/', $scalar);
        $processedLines = [];
        $includesLineBreak = false;
        foreach ($lines as $key => $line) {
            if ($key === 0) {
                $line = substr(rtrim($line), 1);
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

            if ($line === '') {
                $line = "\n";
                $includesLineBreak = true;
            }

            $processedLines[] = $line;
        }
        $finalScalar = self::handleSingleQuoteEscaping(implode(' ', $processedLines));
        if ($includesLineBreak) {
            $finalScalar = str_replace([" \n ", "\n ", " \n"], "\n", $finalScalar);
        }

        $context->addToken(self::createToken($context, TokenType::SINGLE_QUOTED_SCALAR, $finalScalar, [Token::METADATA_WAS_MULTILINE_INPUT => count($lines) > 1]));

        $spacesAfter = $context->getNumberOfCharsCount(' ', $charsToPossibleEnd + 2);
        $context->increasePosition($charsToPossibleEnd + 2 + $spacesAfter);

        return true;
    }

    private static function handleSingleQuoteEscaping(string $scalar): string
    {
        return str_replace("''", "'", $scalar);
    }
}
