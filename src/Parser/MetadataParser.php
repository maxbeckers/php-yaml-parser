<?php

namespace MaxBeckers\YamlParser\Parser;

use MaxBeckers\YamlParser\Lexer\Token;
use MaxBeckers\YamlParser\Lexer\TokenType;
use MaxBeckers\YamlParser\Node\NodeMetadata;

final class MetadataParser
{
    /**
     * @return array{NodeMetadata, ?Token, ?Token}
     */
    public static function parseMetadata(NodeMetadata $metadata, ParserContext $context, bool $isKey = false): array
    {
        $firstMetadataToken = null;
        $lastMetadataToken = null;

        while (true) {
            $token = Parser::peek($context);

            if ($token->is(TokenType::TAG)) {
                $metadata = $metadata->withTag($token->value);
                $firstMetadataToken ??= $token;
                $lastMetadataToken = $token;
                Parser::advance($context);
                continue;
            }

            if ($token->is(TokenType::ANCHOR)) {
                if ($metadata->getAnchor() !== null) {
                    break;
                }

                $metadata = $metadata->withAnchor($token->value);
                $firstMetadataToken ??= $token;
                $lastMetadataToken = $token;
                Parser::advance($context);
                continue;
            }

            break;
        }

        return [$metadata, $firstMetadataToken, $lastMetadataToken];
    }
}
