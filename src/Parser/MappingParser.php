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
                $keyLine = 0;
            } else {
                $metadata = new NodeMetadata();
                MetadataParser::parseMetadata($metadata, $context);
                $keyLine = Parser::peek($context)->line;
                if (Parser::peek($context)->is(TokenType::KEY_INDICATOR)) {
                    $key = new ScalarNode(null, $metadata);
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
            ) {
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
                    && (($valueStartToken->getMetadata()['was_multiline_input'] ?? false) === true)
                ) {
                    $combinedValue = $value->getValue();
                    while (Parser::peek($context)->is(TokenType::PLAIN_SCALAR) && Parser::peek($context)->line === $valueStartToken->line) {
                        $combinedValue .= (string) Parser::peek($context)->value;
                        Parser::advance($context);
                    }

                    if ($combinedValue !== $value->getValue()) {
                        $value = new ScalarNode($combinedValue);
                    }
                }

                if (!$isIndented
                    && !$context->isFlowContext()
                    && $valueStartToken->is(TokenType::PLAIN_SCALAR)
                    && Parser::peek($context)->is(TokenType::INDENT)
                ) {
                    if ($startIndentLevel > 0
                        && Parser::peek($context, 1)->isScalar()
                        && Parser::peek($context, 2)->is(TokenType::KEY_INDICATOR)
                    ) {
                        // Allow compact mapping in sequence entries to continue with another mapping key.
                    } else {
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
            ) {
                throw new ParserException(
                    'Unexpected content on same line as mapping value',
                    Parser::peek($context)
                );
            }

            $mapping->addPair($key, $value);
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
