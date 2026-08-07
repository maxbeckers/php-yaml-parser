<?php

namespace MaxBeckers\YamlParser\Parser;

use MaxBeckers\YamlParser\Api\NodeInterface;
use MaxBeckers\YamlParser\Api\TokenParserInterface;
use MaxBeckers\YamlParser\Lexer\TokenType;
use MaxBeckers\YamlParser\Node\NodeMetadata;
use MaxBeckers\YamlParser\Node\ScalarNode;
use MaxBeckers\YamlParser\Node\SequenceNode;

final class SequenceParser implements TokenParserInterface
{
    public static function supports(ParserContext $context): bool
    {
        return Parser::peek($context)->is(TokenType::SEQUENCE_INDICATOR);
    }

    public static function parse(ParserContext $context, NodeMetadata $metadata = new NodeMetadata(), bool $isKey = false): NodeInterface
    {
        $sequence = new SequenceNode([], $metadata);

        $startIndentLevel = $context->getIndentationLevel();
        while (!Parser::isAtEnd($context) && Parser::peek($context)->is(TokenType::SEQUENCE_INDICATOR)) {
            $sequenceToken = Parser::advance($context);
            while (Parser::peek($context)->is(TokenType::INDENT)) {
                Parser::handleIndent($context);
            }

            $metadata = new NodeMetadata();
            MetadataParser::parseMetadata($metadata, $context);

            $hasDeferredTaggedBlockNode = $metadata->getTag() !== null
                && Parser::peek($context)->is(TokenType::DEDENT)
                && Parser::peek($context, 1)->is(TokenType::INDENT)
                && Parser::peek($context, 2)->isOneOf(TokenType::SEQUENCE_INDICATOR, TokenType::EXPLICIT_KEY);

            if (Parser::peek($context)->is(TokenType::DEDENT)
                && !$hasDeferredTaggedBlockNode
                || (Parser::peek($context)->is(TokenType::SEQUENCE_INDICATOR)
                    && Parser::peek($context)->line > $sequenceToken->line)
            ) {
                $item = new ScalarNode(null, $metadata);
            } else {
                $item = Parser::parseValue($context, $metadata);
            }
            $sequence->addItem($item);

            while (Parser::peek($context)->is(TokenType::DEDENT)) {
                if ($context->getIndentationLevel() === $startIndentLevel) {
                    break;
                }
                Parser::handleDedent($context);
            }

            while (Parser::peek($context)->is(TokenType::INDENT)
                && Parser::peek($context, 1)->is(TokenType::SEQUENCE_INDICATOR)
                && Parser::peek($context, 1)->column <= $sequenceToken->column
            ) {
                Parser::handleIndent($context);
            }
        }

        return $sequence;
    }

}
