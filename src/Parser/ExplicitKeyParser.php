<?php

namespace MaxBeckers\YamlParser\Parser;

use MaxBeckers\YamlParser\Lexer\TokenType;
use MaxBeckers\YamlParser\Node\MappingNode;
use MaxBeckers\YamlParser\Node\ScalarNode;

final class ExplicitKeyParser
{
    public static function parse(ParserContext $context, MappingNode $mappingNode, bool $isKey = false): void
    {
        Parser::advance($context);

        $startIndentLevel = $context->getIndentationLevel();
        if (Parser::peek($context)->is(TokenType::INDENT)) {
            Parser::handleIndent($context);
        }
        if (Parser::peek($context)->is(TokenType::DEDENT)) {
            Parser::handleDedent($context);
        }

        if (Parser::peek($context)->isOneOf(TokenType::MAPPING_END, TokenType::SEQUENCE_END)) {
            $mappingNode->addPair(new ScalarNode(null), new ScalarNode(null));

            return;
        }

        $context->setIsExplicitKey(true);
        if (!$context->isFlowContext() && Parser::peek($context)->isScalar()) {
            $key = ScalarParser::parse(context: $context);
        } else {
            $key = Parser::parseValue(context: $context);
        }
        $context->setIsExplicitKey(false);

        if ($key instanceof ScalarNode) {
            $combinedKey = $key->getValue();
            $didCombine = false;

            while (Parser::peek($context)->is(TokenType::PLAIN_SCALAR)
                && Parser::peek($context, 1)->is(TokenType::KEY_INDICATOR)
            ) {
                $combinedKey .= ' ' . Parser::peek($context)->value;
                Parser::advance($context);
                $didCombine = true;
            }

            if ($didCombine) {
                $key = new ScalarNode($combinedKey, $key->getMetadata());
            }
        }

        $dedentOffset = 0;
        while (Parser::peek($context, $dedentOffset)->is(TokenType::DEDENT)) {
            $dedentOffset++;
        }

        $hasValueIndicatorAfterDedent = Parser::peek($context, $dedentOffset)->is(TokenType::KEY_INDICATOR);
        if ($hasValueIndicatorAfterDedent) {
            while (Parser::peek($context)->is(TokenType::DEDENT)
                && $context->getIndentationLevel() > $startIndentLevel
            ) {
                Parser::handleDedent($context);
            }
        } elseif (Parser::peek($context)->is(TokenType::DEDENT)
            && $context->getIndentationLevel() !== $startIndentLevel
        ) {
            Parser::handleDedent($context);
        }

        if (!$hasValueIndicatorAfterDedent
            && Parser::peek($context)->isOneOf(TokenType::EXPLICIT_KEY, TokenType::DEDENT, TokenType::SEQUENCE_END, TokenType::MAPPING_END, TokenType::DOCUMENT_END, TokenType::EOF)
        ) {
            $value = new ScalarNode(null);
        } else {
            if (Parser::peek($context)->is(TokenType::KEY_INDICATOR)) {
                Parser::advance($context);
            }

            $isIndented = false;
            if (Parser::peek($context)->is(TokenType::INDENT)) {
                Parser::handleIndent($context);
                $isIndented = true;
            }
            $value = Parser::parseValue($context);
            if ($isIndented && Parser::peek($context)->is(TokenType::DEDENT)) {
                Parser::handleDedent($context);
            }
        }
        $mappingNode->addPair($key, $value);

        if (Parser::peek($context)->is(TokenType::DEDENT)) {
            if ($context->getIndentationLevel() !== $startIndentLevel) {
                Parser::handleDedent($context);
            }
        }
    }
}
