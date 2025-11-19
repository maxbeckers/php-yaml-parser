<?php

namespace MaxBeckers\YamlParser\Lexer;

use MaxBeckers\YamlParser\Exception\LexerException;
use MaxBeckers\YamlParser\Lexer\Scanner\AliasScanner;
use MaxBeckers\YamlParser\Lexer\Scanner\AnchorScanner;
use MaxBeckers\YamlParser\Lexer\Scanner\CollectionScanner;
use MaxBeckers\YamlParser\Lexer\Scanner\CommentScanner;
use MaxBeckers\YamlParser\Lexer\Scanner\DirectiveScanner;
use MaxBeckers\YamlParser\Lexer\Scanner\DocumentScanner;
use MaxBeckers\YamlParser\Lexer\Scanner\DoubleQuotedScalarScanner;
use MaxBeckers\YamlParser\Lexer\Scanner\FoldedBlockScanner;
use MaxBeckers\YamlParser\Lexer\Scanner\IndentationScanner;
use MaxBeckers\YamlParser\Lexer\Scanner\LiteralBlockScanner;
use MaxBeckers\YamlParser\Lexer\Scanner\PlainScalarScanner;
use MaxBeckers\YamlParser\Lexer\Scanner\SingleQuotedScalarScanner;
use MaxBeckers\YamlParser\Lexer\Scanner\TagScanner;

final class Lexer
{
    private static array $SCANNERS = [
        IndentationScanner::class,
        DoubleQuotedScalarScanner::class,
        SingleQuotedScalarScanner::class,
        DocumentScanner::class,
        DirectiveScanner::class,
        TagScanner::class,
        AnchorScanner::class,
        AliasScanner::class,
        CollectionScanner::class,
        LiteralBlockScanner::class,
        FoldedBlockScanner::class,
        CommentScanner::class,
        PlainScalarScanner::class,
    ];

    public static function tokenize(LexerContext $context): array
    {
        while (!$context->isAtEndOfFile()) {
            $currentChar = $context->getInputPart(1);
            foreach (self::$SCANNERS as $scannerClass) {
                if ($scannerClass::scan($context, $currentChar)) {
                    continue 2;
                }
            }

            throw new LexerException("No scanner could process the input at line {$context->getLine()}, column {$context->getColumn()} (char: '" . $currentChar . "')");
        }

        if ($context->getCurrentIndent() > -1) {
            DocumentScanner::resetMode($context);
        }

        return $context->getTokens();
    }
}
