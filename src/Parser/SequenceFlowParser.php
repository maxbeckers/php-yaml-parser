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
        $startToken = Parser::advance($context);
        $startIndentLevel = $context->getIndentationLevel();
        $sequence = new SequenceNode([], $metadata);

        while (!Parser::peek($context)->is(TokenType::SEQUENCE_END)) {
            if (Parser::isAtEnd($context)) {
                throw new ParserException(
                    'Unexpected end of input in flow sequence',
                    Parser::peek($context)
                );
            }

            if ($startToken->column > 0
                && Parser::peek($context)->line > $startToken->line
                && Parser::peek($context)->column === 0
                && !Parser::peek($context)->is(TokenType::SEQUENCE_END)
            ) {
                throw new ParserException(
                    'Invalid indentation in flow sequence',
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

            $nextToken = Parser::peek($context);
            if ($nextToken->is(TokenType::PLAIN_SCALAR) && $nextToken->value === '-') {
                throw new ParserException(
                    "Bare '-' is not allowed in flow sequence",
                    $nextToken
                );
            }

            $sequence->addItem(Parser::parseValue($context));

            if (Parser::peek($context)->is(TokenType::FLOW_SEPARATOR)) {
                $separator = Parser::advance($context);

                if ($startToken->column > 0
                    && Parser::peek($context)->line > $separator->line
                    && Parser::peek($context)->column <= 1
                    && !Parser::peek($context)->is(TokenType::SEQUENCE_END)
                ) {
                    throw new ParserException(
                        'Invalid indentation in flow sequence',
                        Parser::peek($context)
                    );
                }

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

        $endToken = Parser::advance($context);

        if (false === $isKey
            && Parser::peek($context)->is(TokenType::KEY_INDICATOR)
            && !$context->isExplicitKey()
            && $startToken->line !== $endToken->line
        ) {
            throw new ParserException(
                'Flow collection keys cannot span multiple lines',
                Parser::peek($context)
            );
        }

        while (Parser::peek($context)->is(TokenType::DEDENT)
            && $context->getIndentationLevel() > $startIndentLevel
        ) {
            Parser::handleDedent($context);
        }

        if (false === $isKey && Parser::peek($context)->is(TokenType::KEY_INDICATOR) && !$context->isExplicitKey()) {
            $context->exitFlowContext();

            return MappingParser::parse(context: $context, explicitKey: $sequence);
        }

        $context->exitFlowContext();

        return $sequence;
    }

}
