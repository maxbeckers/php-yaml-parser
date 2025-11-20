<?php

namespace MaxBeckers\YamlParser\Parser;

use MaxBeckers\YamlParser\Api\NodeInterface;
use MaxBeckers\YamlParser\Api\TokenParserInterface;
use MaxBeckers\YamlParser\Exception\ParserException;
use MaxBeckers\YamlParser\Lexer\TokenType;
use MaxBeckers\YamlParser\Node\MappingNode;
use MaxBeckers\YamlParser\Node\NodeMetadata;
use MaxBeckers\YamlParser\Node\ScalarNode;

final class MappingParser implements TokenParserInterface
{
    public static function supports(ParserContext $context): bool
    {
        $peek = Parser::peek($context)->is(TokenType::INDENT) ? 1 : 0;

        return Parser::peek($context, $peek)->is(TokenType::EXPLICIT_KEY) || self::isMapping($context, $peek);
    }

    public static function parse(ParserContext $context, NodeMetadata $metadata = new NodeMetadata(), bool $isKey = false, ?NodeInterface $explicitKey = null): NodeInterface
    {
        $mapping = new MappingNode([], $metadata);

        $startIndentLevel = $context->getIndentationLevel();
        while (!Parser::isAtEnd($context)) {
            if (Parser::peek($context)->is(TokenType::DEDENT)) {
                if ($context->getIndentationLevel() === $startIndentLevel) {
                    break;
                }
                Parser::handleDedent($context);
                if ($context->getIndentationLevel() === $startIndentLevel) {
                    break;
                }
                continue;
            }
            if (Parser::peek($context)->is(TokenType::INDENT)) {
                Parser::handleIndent($context);
                continue;
            }

            if (in_array(Parser::peek($context)->type, [TokenType::EOF, TokenType::DOCUMENT_END, TokenType::DOCUMENT_START, TokenType::FLOW_SEPARATOR, TokenType::SEQUENCE_END], true)) {
                break;
            }

            if (Parser::peek($context)->is(TokenType::EXPLICIT_KEY)) {
                ExplicitKeyParser::parse($context, $mapping);
                continue;
            }

            if ($explicitKey !== null) {
                $key = $explicitKey;
                $explicitKey = null;
            } else {
                $metadata = new NodeMetadata();
                MetadataParser::parseMetadata($metadata, $context);
                if (Parser::peek($context)->isScalar()) {
                    $key = ScalarParser::parse($context, $metadata, true);
                } else {
                    $key = Parser::parseValue($context, $metadata, true);
                }
            }
            if (!Parser::peek($context)->is(TokenType::KEY_INDICATOR)) {
                throw new ParserException(
                    "Expected ':' after key in mapping",
                    Parser::peek($context)
                );
            }
            Parser::advance($context);

            if (Parser::peek($context)->isScalar() && Parser::peek($context, 1)->is(TokenType::KEY_INDICATOR) && !$context->isExplicitKey()) {
                $value = new ScalarNode(null);
            } else {
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

            $mapping->addPair($key, $value);
        }

        return $mapping;
    }

    private static function isMapping(ParserContext $context, int $peek = 0): bool
    {
        if (!Parser::peek($context, $peek)->isScalar()) {
            return false;
        }

        if (!Parser::peek($context, $peek + 1)->is(TokenType::KEY_INDICATOR)) {
            return false;
        }

        if ($context->isExplicitKey() && $context->isFlowContext()) {
            return false;
        }

        return true;
    }
}
