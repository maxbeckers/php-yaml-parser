<?php

namespace MaxBeckers\YamlParser\Parser;

use MaxBeckers\YamlParser\Api\NodeInterface;
use MaxBeckers\YamlParser\Api\TokenParserInterface;
use MaxBeckers\YamlParser\Lexer\TokenType;
use MaxBeckers\YamlParser\Node\NodeMetadata;
use MaxBeckers\YamlParser\Node\ScalarNode;

final class AliasParser implements TokenParserInterface
{
    public static function supports(ParserContext $context): bool
    {
        return Parser::peek($context)->is(TokenType::ALIAS);
    }

    public static function parse(ParserContext $context, NodeMetadata $metadata = new NodeMetadata(), bool $isKey = false): NodeInterface
    {
        $metadata->setAlias(Parser::peek($context)->value);
        Parser::advance($context);

        return new ScalarNode(null, $metadata);
    }

}
