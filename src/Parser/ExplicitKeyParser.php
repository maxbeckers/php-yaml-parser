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

        if (Parser::peek($context)->is(TokenType::DEDENT)) {
            if ($context->getIndentationLevel() !== $startIndentLevel) {
                Parser::handleDedent($context);
            }
        }

        if (Parser::peek($context)->is(TokenType::EXPLICIT_KEY)) {
            $value = new ScalarNode(null);
        } else {
            if (Parser::peek($context)->is(TokenType::KEY_INDICATOR)) {
                Parser::advance($context);
            }

            $value = Parser::parseValue($context);
        }
        $mappingNode->addPair($key, $value);

        if (Parser::peek($context)->is(TokenType::DEDENT)) {
            if ($context->getIndentationLevel() !== $startIndentLevel) {
                Parser::handleDedent($context);
            }
        }
    }
}
