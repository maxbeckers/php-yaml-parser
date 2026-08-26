<?php

namespace MaxBeckers\YamlParser\Parser;

use MaxBeckers\YamlParser\Api\NodeInterface;
use MaxBeckers\YamlParser\Api\TokenParserInterface;
use MaxBeckers\YamlParser\Exception\ParserException;
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
        if ($metadata->getAnchor() !== null) {
            throw new ParserException(
                'Anchor and alias cannot be defined on the same node',
                Parser::peek($context)
            );
        }

        $metadata = $metadata->withAlias(Parser::peek($context)->value);
        Parser::advance($context);

        return new ScalarNode(null, $metadata);
    }

}
