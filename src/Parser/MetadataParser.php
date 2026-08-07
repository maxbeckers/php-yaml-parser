<?php

namespace MaxBeckers\YamlParser\Parser;

use MaxBeckers\YamlParser\Lexer\Token;
use MaxBeckers\YamlParser\Lexer\TokenType;
use MaxBeckers\YamlParser\Node\NodeMetadata;

final class MetadataParser
{
    public static function parseMetadata(NodeMetadata $metadata, ParserContext $context, bool $isKey = false): ?Token
    {
        $lastMetadataToken = null;

        while (true) {
            $token = Parser::peek($context);

            if ($token->is(TokenType::TAG)) {
                $metadata->setTag($token->value);
                $lastMetadataToken = $token;
                Parser::advance($context);
                continue;
            }

            if ($token->is(TokenType::ANCHOR)) {
                if ($metadata->getAnchor() !== null) {
                    break;
                }

                $metadata->setAnchor($token->value);
                $lastMetadataToken = $token;
                Parser::advance($context);
                continue;
            }

            break;
        }

        return $lastMetadataToken;
    }
}
