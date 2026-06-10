<?php

namespace MaxBeckers\YamlParser\Parser;

use MaxBeckers\YamlParser\Api\NodeInterface;
use MaxBeckers\YamlParser\Api\TokenParserInterface;
use MaxBeckers\YamlParser\Exception\ParserException;
use MaxBeckers\YamlParser\Lexer\TokenType;
use MaxBeckers\YamlParser\Node\NodeMetadata;
use MaxBeckers\YamlParser\Node\SequenceNode;

final class SequenceFlowParser implements TokenParserInterface
{
    public static function supports(ParserContext $context): bool
    {
        return Parser::peek($context)->is(TokenType::SEQUENCE_START);
    }

    public static function parse(ParserContext $context, NodeMetadata $metadata = new NodeMetadata(), bool $isKey = false): NodeInterface
    {
        $context->enterFlowContext();
        Parser::advance($context);
        $startIndentLevel = $context->getIndentationLevel();
        $sequence = new SequenceNode([], $metadata);

        while (!Parser::peek($context)->is(TokenType::SEQUENCE_END)) {
            if (Parser::isAtEnd($context)) {
                throw new ParserException(
                    'Unexpected end of input in flow sequence',
                    Parser::peek($context)
                );
            }

            if (Parser::peek($context)->is(TokenType::INDENT)) {
                Parser::handleIndent($context);
                continue;
            }

            if (Parser::peek($context)->is(TokenType::DEDENT)
                && $context->getIndentationLevel() > $startIndentLevel
            ) {
                Parser::handleDedent($context);
                continue;
            }

            $sequence->addItem(Parser::parseValue($context));

            if (Parser::peek($context)->is(TokenType::FLOW_SEPARATOR)) {
                Parser::advance($context);

                if (Parser::peek($context)->is(TokenType::DEDENT)) {
                    Parser::handleDedent($context);
                }

                if (Parser::peek($context)->is(TokenType::INDENT)) {
                    Parser::handleIndent($context);
                }

                if (Parser::peek($context)->is(TokenType::SEQUENCE_END)) {
                    break;
                }
            } elseif (!Parser::peek($context)->is(TokenType::SEQUENCE_END)) {
                throw new ParserException(
                    "Expected ',' or ']' in flow sequence",
                    Parser::peek($context)
                );
            }
        }

        if (!Parser::peek($context)->is(TokenType::SEQUENCE_END)) {
            throw new ParserException(
                "Expected ']' to close flow sequence",
                Parser::peek($context)
            );
        }

        Parser::advance($context);

        while (Parser::peek($context)->is(TokenType::DEDENT)
            && $context->getIndentationLevel() > $startIndentLevel
        ) {
            Parser::handleDedent($context);
        }

        if (false === $isKey && Parser::peek($context)->is(TokenType::KEY_INDICATOR) && !$context->isExplicitKey()) {
            return MappingParser::parse(context: $context, explicitKey: $sequence);
        }

        $context->exitFlowContext();

        return $sequence;
    }

}
