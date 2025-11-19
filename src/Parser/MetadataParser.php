<?php

namespace MaxBeckers\YamlParser\Parser;

use MaxBeckers\YamlParser\Lexer\TokenType;
use MaxBeckers\YamlParser\Node\NodeMetadata;

final class MetadataParser
{
    public static function parseMetadata(NodeMetadata $metadata, ParserContext $context, bool $isKey = false): void
    {
        $token = Parser::peek($context);
        if ($token->is(TokenType::TAG)) {
            $metadata->setTag($token->value);
            Parser::advance($context);
            $token = Parser::peek($context);
        }

        if ($token->is(TokenType::ANCHOR)) {
            $metadata->setAnchor($token->value);
            Parser::advance($context);
        }
    }
}
