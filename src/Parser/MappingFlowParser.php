<?php

namespace MaxBeckers\YamlParser\Parser;

use MaxBeckers\YamlParser\Api\NodeInterface;
use MaxBeckers\YamlParser\Api\TokenParserInterface;
use MaxBeckers\YamlParser\Exception\ParserException;
use MaxBeckers\YamlParser\Lexer\TokenType;
use MaxBeckers\YamlParser\Node\MappingNode;
use MaxBeckers\YamlParser\Node\NodeMetadata;
use MaxBeckers\YamlParser\Node\ScalarNode;

final class MappingFlowParser implements TokenParserInterface
{
    public static function supports(ParserContext $context): bool
    {
        return Parser::peek($context)->is(TokenType::MAPPING_START);
    }

    public static function parse(ParserContext $context, NodeMetadata $metadata = new NodeMetadata(), bool $isKey = false): NodeInterface
    {
        $context->enterFlowContext();
        Parser::advance($context);
        $mapping = new MappingNode([], $metadata);

        $indentLevel = $context->getIndentationLevel();
        while (!Parser::peek($context)->is(TokenType::MAPPING_END)) {
            if (Parser::isAtEnd($context)) {
                throw new ParserException(
                    'Unexpected end of input in flow mapping',
                    Parser::peek($context)
                );
            }

            if (Parser::peek($context)->is(TokenType::INDENT)) {
                $indentLevel++;
                Parser::handleIndent($context);
                continue;
            }

            if (Parser::peek($context)->is(TokenType::DEDENT)) {
                $indentLevel--;
                if ($context->getIndentationLevel() >= $indentLevel) {
                    Parser::handleDedent($context);
                }
                continue;
            }

            if (Parser::peek($context)->is(TokenType::EXPLICIT_KEY)) {
                ExplicitKeyParser::parse($context, $mapping);
                if (Parser::peek($context)->is(TokenType::FLOW_SEPARATOR)) {
                    Parser::advance($context);
                }
                continue;
            }

            $metadata = new NodeMetadata();
            MetadataParser::parseMetadata($metadata, $context);
            if (Parser::peek($context)->isScalar()) {
                $key = ScalarParser::parse($context, $metadata, true);
            } else {
                $key = Parser::parseValue($context, $metadata, true);
            }
            if (Parser::peek($context)->isOneOf(TokenType::FLOW_SEPARATOR, TokenType::MAPPING_END)) {
                $mapping->addPair($key, new ScalarNode(null));
            } else {
                if (!Parser::peek($context)->is(TokenType::KEY_INDICATOR)) {
                    throw new ParserException(
                        "Expected ':' in flow mapping",
                        Parser::peek($context)
                    );
                }
                Parser::advance($context);
                if (Parser::peek($context)->isOneOf(TokenType::FLOW_SEPARATOR, TokenType::MAPPING_END)) {
                    $mapping->addPair($key, new ScalarNode(null));
                } else {
                    $value = Parser::parseValue($context, $metadata);
                    $mapping->addPair($key, $value);
                }
            }

            if (Parser::peek($context)->is(TokenType::DEDENT)) {
                $indentLevel--;
                if ($context->getIndentationLevel() >= $indentLevel) {
                    Parser::handleDedent($context);
                }
            }

            if (Parser::peek($context)->is(TokenType::FLOW_SEPARATOR)) {
                Parser::advance($context);

                if (Parser::peek($context)->is(TokenType::DEDENT)) {
                    Parser::handleDedent($context);
                }

                if (Parser::peek($context)->is(TokenType::MAPPING_END)) {
                    break;
                }
            } elseif (!Parser::peek($context)->is(TokenType::MAPPING_END)) {
                throw new ParserException(
                    "Expected ',' or '}' in flow sequence",
                    Parser::peek($context)
                );
            }
        }

        if (!Parser::peek($context)->is(TokenType::MAPPING_END)) {
            throw new ParserException(
                "Expected '}' to close flow sequence",
                Parser::peek($context)
            );
        }

        Parser::advance($context);

        if (false === $isKey && Parser::peek($context)->is(TokenType::KEY_INDICATOR) && !$context->isExplicitKey()) {
            return MappingParser::parse(context: $context, explicitKey: $mapping);
        }

        $context->exitFlowContext();

        return $mapping;
    }
}
