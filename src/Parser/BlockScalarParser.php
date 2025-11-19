<?php

namespace MaxBeckers\YamlParser\Parser;

use MaxBeckers\YamlParser\Api\NodeInterface;
use MaxBeckers\YamlParser\Api\TokenParserInterface;
use MaxBeckers\YamlParser\Lexer\TokenType;
use MaxBeckers\YamlParser\Node\NodeMetadata;
use MaxBeckers\YamlParser\Node\ScalarNode;

final class BlockScalarParser implements TokenParserInterface
{
    public static function supports(ParserContext $context): bool
    {
        $token = Parser::peek($context);

        return $token->is(TokenType::LITERAL_SCALAR) || $token->is(TokenType::FOLDED_SCALAR);
    }

    public static function parse(ParserContext $context, NodeMetadata $metadata = new NodeMetadata(), bool $isKey = false): NodeInterface
    {
        $value = Parser::peek($context)->value;
        Parser::advance($context);

        return new ScalarNode($value, $metadata);
    }
}
