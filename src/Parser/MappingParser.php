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

        while (Parser::peek($context, $peek)->isOneOf(TokenType::TAG, TokenType::ANCHOR)) {
            $peek++;
        }

        return Parser::peek($context, $peek)->is(TokenType::EXPLICIT_KEY) || self::isMapping($context, $peek);
    }

    public static function parse(ParserContext $context, NodeMetadata $metadata = new NodeMetadata(), bool $isKey = false, ?NodeInterface $explicitKey = null): NodeInterface
    {
        $mapping = new MappingNode([], $metadata);

        $startIndentLevel = $context->getIndentationLevel();
        while (!Parser::isAtEnd($context)) {
            $currentToken = Parser::peek($context);
            $currentType  = $currentToken->type;

            if ($currentType === TokenType::DEDENT) {
                if ($context->getIndentationLevel() === $startIndentLevel) {
                    break;
                }
                Parser::handleDedent($context);
                if ($context->getIndentationLevel() === $startIndentLevel) {
                    break;
                }
                continue;
            }
            if ($currentType === TokenType::INDENT) {
                Parser::handleIndent($context);
                continue;
            }

            if ($currentType === TokenType::EOF
                || $currentType === TokenType::DOCUMENT_END
                || $currentType === TokenType::DOCUMENT_START
                || $currentType === TokenType::FLOW_SEPARATOR
                || $currentType === TokenType::SEQUENCE_END
            ) {
                break;
            }

            if ($currentType === TokenType::EXPLICIT_KEY) {
                ExplicitKeyParser::parse($context, $mapping);
                continue;
            }

            if ($explicitKey !== null) {
                $key = $explicitKey;
                $explicitKey = null;
                $keyLine = 0;
                $keyWasImplicitNull = false;
            } else {
                $metadata = new NodeMetadata();
                [$metadata, $metadataToken] = MetadataParser::parseMetadata($metadata, $context);
                if ($context->shouldPreserveMetadata()) {
                    $positionToken = $metadataToken ?? Parser::peek($context);
                    $metadata = $metadata->withPosition(
                        Parser::tokenLine($positionToken),
                        Parser::tokenColumn($positionToken)
                    );
                }
                $keyLine = Parser::peek($context)->line;
                $keyWasImplicitNull = false;
                if (Parser::peek($context)->is(TokenType::KEY_INDICATOR)) {
                    $key = new ScalarNode(null, $metadata);
                    $keyWasImplicitNull = true;
                } elseif (Parser::peek($context)->isScalar()) {
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
            $keyIndicatorToken = Parser::advance($context);

            if (Parser::isAtEnd($context) || Parser::peek($context)->is(TokenType::DOCUMENT_END) || Parser::peek($context)->is(TokenType::DOCUMENT_START)) {
                $mapping->addPair($key, new ScalarNode(null));
                break;
            }

            if (Parser::peek($context)->is(TokenType::DEDENT)) {
                $mapping->addPair($key, new ScalarNode(null));
                continue;
            }

            if (Parser::peek($context)->isScalar()
                && Parser::peek($context, 1)->is(TokenType::KEY_INDICATOR)
                && !$context->isExplicitKey()
                && Parser::peek($context)->line > $keyIndicatorToken->line
            ) {
                $value = new ScalarNode(null);
            } elseif (!$context->isFlowContext()
                && !$context->isExplicitKey()
                && $keyLine === $keyIndicatorToken->line
                && Parser::peek($context)->isScalar()
                && Parser::peek($context, 1)->is(TokenType::KEY_INDICATOR)
                && Parser::peek($context)->line === $keyIndicatorToken->line
                && Parser::peek($context, 1)->line === $keyIndicatorToken->line
                && $context->isStrictMode()
            ) {
                $hasValueAfterSecondIndicator = !Parser::peek($context, 2)->isOneOf(
                    TokenType::DEDENT,
                    TokenType::SEQUENCE_END,
                    TokenType::MAPPING_END,
                    TokenType::DOCUMENT_END,
                    TokenType::DOCUMENT_START,
                    TokenType::FLOW_SEPARATOR,
                    TokenType::EOF,
                );
                if ($keyWasImplicitNull && $hasValueAfterSecondIndicator) {
                    $value = Parser::parseValue($context);
                    if (Parser::peek($context)->is(TokenType::DEDENT)) {
                        Parser::handleDedent($context);
                    }
                    $mapping->addPair($key, $value);
                    continue;
                }

                throw new ParserException(
                    'Unexpected mapping key in scalar value in block mapping',
                    Parser::peek($context, 1)
                );
            } else {
                if (!$context->isFlowContext()
                    && Parser::peek($context)->is(TokenType::ANCHOR)
                    && Parser::peek($context, 1)->is(TokenType::SEQUENCE_INDICATOR)
                    && Parser::peek($context, 1)->line > Parser::peek($context)->line
                    && Parser::peek($context)->column === 0
                    && $context->isStrictMode()
                ) {
                    throw new ParserException(
                        'Anchor on a separate line cannot directly prefix a block sequence value in a mapping',
                        Parser::peek($context)
                    );
                }

                $isIndented = false;
                $valueStartToken = Parser::peek($context);
                if (Parser::peek($context)->is(TokenType::INDENT)) {
                    Parser::handleIndent($context);
                    $isIndented = true;
                }
                $value = Parser::parseValue($context);

                if (!$context->isFlowContext()
                    && $valueStartToken->is(TokenType::PLAIN_SCALAR)
                    && $value instanceof ScalarNode
                    && $valueStartToken->wasMultilineInput()
                ) {
                    $combinedValue = $value->getValue();
                    while (Parser::peek($context)->is(TokenType::PLAIN_SCALAR) && Parser::peek($context)->line === $valueStartToken->line) {
                        $combinedValue .= (string) Parser::peek($context)->value;
                        Parser::advance($context);
                    }

                    if ($combinedValue !== $value->getValue()) {
                        $value = new ScalarNode($combinedValue, $value->getMetadata());
                    }
                }

                if (!$isIndented
                    && !$context->isFlowContext()
                    && $valueStartToken->is(TokenType::PLAIN_SCALAR)
                    && Parser::peek($context)->is(TokenType::INDENT)
                    && $context->isStrictMode()
                ) {
                    if (!(
                        $startIndentLevel > 0
                        && Parser::peek($context, 1)->isScalar()
                        && Parser::peek($context, 2)->is(TokenType::KEY_INDICATOR)
                    )) {
                        throw new ParserException(
                            'Unexpected indented content after scalar value in block mapping',
                            Parser::peek($context)
                        );
                    }
                }

                if (!$context->isFlowContext()
                    && !$context->isExplicitKey()
                    && $valueStartToken->isScalar()
                    && Parser::peek($context)->is(TokenType::KEY_INDICATOR)
                    && Parser::peek($context)->line === $valueStartToken->line
                    && $context->isStrictMode()
                ) {
                    throw new ParserException(
                        'Unexpected mapping indicator in scalar value in block mapping',
                        Parser::peek($context)
                    );
                }

                if ($isIndented && Parser::peek($context)->is(TokenType::DEDENT)) {
                    Parser::handleDedent($context);
                }
            }

            // Reject a new mapping key that appears on the same line as the current key indicator
            if (!$context->isFlowContext()
                && !$context->isExplicitKey()
                && Parser::peek($context)->isScalar()
                && Parser::peek($context)->line === $keyIndicatorToken->line
                && $context->isStrictMode()
            ) {
                throw new ParserException(
                    'Unexpected content on same line as mapping value',
                    Parser::peek($context)
                );
            }

            $mapping->addPair($key, $value);

            if ($isKey
                && !$context->isFlowContext()
                && Parser::peek($context)->is(TokenType::KEY_INDICATOR)
            ) {
                break;
            }
        }

        return $mapping;
    }

    private static function isMapping(ParserContext $context, int $peek = 0): bool
    {
        if (Parser::peek($context, $peek)->is(TokenType::KEY_INDICATOR)) {
            return true;
        }

        if (Parser::peek($context, $peek)->is(TokenType::ALIAS)) {
            return Parser::peek($context, $peek + 1)->is(TokenType::KEY_INDICATOR);
        }

        if (Parser::peek($context, $peek)->isOneOf(TokenType::SEQUENCE_START, TokenType::MAPPING_START)) {
            return self::hasKeyIndicatorAfterComplexKey($context, $peek);
        }

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

    private static function hasKeyIndicatorAfterComplexKey(ParserContext $context, int $peek): bool
    {
        $sequenceDepth = 0;
        $mappingDepth = 0;

        for ($offset = $peek; $offset < $peek + 256; $offset++) {
            $token = Parser::peek($context, $offset);

            if ($token->is(TokenType::EOF)) {
                return false;
            }

            if ($token->is(TokenType::SEQUENCE_START)) {
                $sequenceDepth++;
                continue;
            }

            if ($token->is(TokenType::MAPPING_START)) {
                $mappingDepth++;
                continue;
            }

            if ($token->is(TokenType::SEQUENCE_END)) {
                if ($sequenceDepth === 0) {
                    return false;
                }
                $sequenceDepth--;
                continue;
            }

            if ($token->is(TokenType::MAPPING_END)) {
                if ($mappingDepth === 0) {
                    return false;
                }
                $mappingDepth--;
                continue;
            }

            if ($sequenceDepth === 0 && $mappingDepth === 0) {
                if ($token->is(TokenType::KEY_INDICATOR)) {
                    return true;
                }

                if ($token->isOneOf(TokenType::FLOW_SEPARATOR, TokenType::DOCUMENT_END, TokenType::DOCUMENT_START, TokenType::DEDENT)) {
                    return false;
                }
            }
        }

        return false;
    }
}
