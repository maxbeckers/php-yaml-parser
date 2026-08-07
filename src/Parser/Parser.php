<?php

namespace MaxBeckers\YamlParser\Parser;

use MaxBeckers\YamlParser\Api\NodeInterface;
use MaxBeckers\YamlParser\Exception\ParserException;
use MaxBeckers\YamlParser\Lexer\Token;
use MaxBeckers\YamlParser\Lexer\TokenType;
use MaxBeckers\YamlParser\Node\DocumentNode;
use MaxBeckers\YamlParser\Node\NodeMetadata;
use MaxBeckers\YamlParser\Node\ScalarNode;
use MaxBeckers\YamlParser\Node\YamlNode;
use MaxBeckers\YamlParser\Format\Version;

final class Parser
{
    private static array $PARSERS = [
        AliasParser::class,
        SequenceFlowParser::class,
        MappingFlowParser::class,
        BlockScalarParser::class,
        MappingParser::class,
        SequenceParser::class,
        ScalarParser::class,
    ];

    public static function parse(ParserContext $context): NodeInterface
    {
        $yamlNode = new YamlNode();

        while (!$context->getStream()->isAtEnd()) {
            if (self::match($context, TokenType::DOCUMENT_START)) {
                $version = self::peek($context)->getMetadata()[Token::METADATA_VERSION] ?? Version::VERSION_1_2;
                $context->setYamlVersion($version);
                self::advance($context);
                continue;
            }
            if (self::match($context, TokenType::DOCUMENT_END)) {
                self::advance($context);
                continue;
            }

            $node = self::parseValue($context);

            $yamlNode->addDocument(new DocumentNode($node));

            while (self::peek($context)->is(TokenType::DEDENT)) {
                self::handleDedent($context);
            }

            if (!self::peek($context)->isOneOf(TokenType::EOF, TokenType::DOCUMENT_END, TokenType::DOCUMENT_START)) {
                throw new ParserException(
                    'Unexpected content after document node',
                    self::peek($context)
                );
            }
        }

        return $yamlNode;
    }

    public static function parseValue(ParserContext $context, $metadata = new NodeMetadata(), bool $isKey = false): NodeInterface
    {
        MetadataParser::parseMetadata($metadata, $context);

        $metadataIndentWrapped = false;
        if (($metadata->getTag() !== null || $metadata->getAnchor() !== null)
            && self::peek($context)->is(TokenType::INDENT)
        ) {
            self::handleIndent($context);
            $metadataIndentWrapped = true;
        }

        if (($metadata->getTag() !== null || $metadata->getAnchor() !== null)
            && self::peek($context)->isOneOf(TokenType::EOF, TokenType::DOCUMENT_END, TokenType::FLOW_SEPARATOR, TokenType::MAPPING_END, TokenType::SEQUENCE_END, TokenType::DEDENT)
        ) {
            if ($metadataIndentWrapped && self::peek($context)->is(TokenType::DEDENT)) {
                self::handleDedent($context);
            }

            return new ScalarNode('',$metadata);
        }

        foreach (self::$PARSERS as $parserClass) {
            if ($parserClass::supports($context)) {
                $node = $parserClass::parse($context, $metadata, $isKey);

                if ($metadataIndentWrapped && self::peek($context)->is(TokenType::DEDENT)) {
                    self::handleDedent($context);
                }

                return $node;
            }
        }

        $token = self::peek($context);
        throw new ParserException(
            "Unexpected token: {$token->type->value}",
            $token
        );
    }

    public static function peek(ParserContext $context, int $peek = 0): Token
    {
        return $context->getStream()->peek($peek) ?? new Token(TokenType::EOF);
    }

    public static function advance(ParserContext $context): Token
    {
        return $context->getStream()->next() ?? new Token(TokenType::EOF);
    }

    public static function isAtEnd(ParserContext $context): bool
    {
        return self::peek($context)->is(TokenType::EOF);
    }

    public static function handleIndent(ParserContext $context): void
    {
        $context->increaseIndentationLevel();
        self::advance($context);
    }

    public static function handleDedent(ParserContext $context): void
    {
        $context->decreaseIndentationLevel();
        self::advance($context);
    }

    private static function match(ParserContext $context, TokenType $type): bool
    {
        return self::peek($context)->is($type);
    }
}
