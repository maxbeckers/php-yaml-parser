<?php

namespace MaxBeckers\YamlParser\Parser;

use MaxBeckers\YamlParser\Api\NodeInterface;
use MaxBeckers\YamlParser\Api\TokenParserInterface;
use MaxBeckers\YamlParser\Exception\ParserException;
use MaxBeckers\YamlParser\Lexer\Token;
use MaxBeckers\YamlParser\Lexer\TokenType;
use MaxBeckers\YamlParser\Node\NodeMetadata;
use MaxBeckers\YamlParser\Node\ScalarNode;
use MaxBeckers\YamlParser\Format\NumberType;
use MaxBeckers\YamlParser\Format\FormatHelper;
use MaxBeckers\YamlParser\Format\Version;

final class ScalarParser implements TokenParserInterface
{
    private static array $NUMBER_TYPE_PATTERNS = [];

    public static function supports(ParserContext $context): bool
    {
        return Parser::peek($context)->isScalar();
    }

    public static function parse(ParserContext $context, NodeMetadata $metadata = new NodeMetadata(), bool $isKey = false): NodeInterface
    {
        if ($isKey) {
            return self::parseKey($context, $metadata);
        }

        $token = Parser::peek($context);

        if ($token->type !== TokenType::PLAIN_SCALAR) {
            $tokenValue = $token->value;
            if ($tokenValue === '' && $context->getYamlVersion() === Version::VERSION_1_1) {
                $tokenValue = null;
            }
            Parser::advance($context);

            return new ScalarNode($tokenValue, $metadata);
        }

        $value = $token->value;

        if (self::isNullValue($context, $value)) {
            $value = null;
        } elseif (self::isBooleanValue($context, $value)) {
            $lowerValue = strtolower($value);
            $value = ($lowerValue === 'true' || $lowerValue === 'yes' || $lowerValue === 'on');
        } else {
            $numberType = self::getNumberType($context, $value);
            if ($numberType !== null) {
                switch ($numberType) {
                    case NumberType::INTEGER:
                        $value = (int) $value;
                        break;
                    case NumberType::FLOAT:
                        $value = strtolower($value);
                        $value = match (true) {
                            strcasecmp($value, '.inf') === 0, strcasecmp($value, '+.inf') === 0 => INF,
                            strcasecmp($value, '-.inf') === 0 => -INF,
                            strcasecmp($value, '.nan') === 0, strcasecmp($value, '+.nan') === 0, strcasecmp($value, '-.nan') === 0 => NAN,
                            default => (float) $value
                        };
                        break;
                    case NumberType::OCTAL:
                        $value = base_convert($value, 8, 10);
                        break;
                    case NumberType::HEXADECIMAL:
                        $value = base_convert($value, 16, 10);
                        break;
                }
            }
        }

        Parser::advance($context);

        return new ScalarNode($value, $metadata);
    }

    private static function parseKey(ParserContext $context, NodeMetadata $metadata = new NodeMetadata()): NodeInterface
    {
        $token = Parser::peek($context);

        $isMultiline = $token->getMetadata()[Token::METADATA_WAS_MULTILINE_INPUT] ?? false;
        $flowQuotedOk = $context->isFlowContext()
            && $token->isOneOf(TokenType::DOUBLE_QUOTED_SCALAR, TokenType::SINGLE_QUOTED_SCALAR, TokenType::PLAIN_SCALAR);
        if ($isMultiline && !$flowQuotedOk) {
            throw new ParserException(
                'Multiline scalars are not allowed as mapping keys.',
                $token
            );
        }

        $value = $token->value;

        if ($value !== null && strlen($value) > 1000) {
            throw new ParserException(
                'Mapping keys cannot be longer than 1000 characters',
                $token
            );
        }

        if ('<<' === $value) {
            $metadata->setIsMergeKey();
        }

        Parser::advance($context);

        return new ScalarNode($value, $metadata);
    }

    private static function isNullValue(ParserContext $context, ?string $value): bool
    {
        if ($value === null) {
            return true;
        }

        $lowerValue = strtolower($value);

        if ($context->getYamlVersion() === Version::VERSION_1_1) {
            return $lowerValue === 'null' || $lowerValue === '~' || $lowerValue === '';
        }

        return $lowerValue === 'null' || $lowerValue === '~';
    }

    private static function isBooleanValue(ParserContext $context, string $value): bool
    {
        $lowerValue = strtolower($value);

        if ($context->getYamlVersion() === Version::VERSION_1_1) {
            return $lowerValue === 'true' || $lowerValue === 'false'
                || $lowerValue === 'yes'  || $lowerValue === 'no'
                || $lowerValue === 'on'   || $lowerValue === 'off';
        }

        return $lowerValue === 'true' || $lowerValue === 'false';
    }

    private static function getNumberType(ParserContext $context, string $value): ?NumberType
    {
        $first = $value[0] ?? '';
        if ($first !== '+' && $first !== '-' && $first !== '.'
            && ($first < '0' || $first > '9')
        ) {
            return null;
        }

        $version = $context->getYamlVersion()->value;

        if (!isset(self::$NUMBER_TYPE_PATTERNS[$version])) {
            if ($context->getYamlVersion() === Version::VERSION_1_1) {
                self::$NUMBER_TYPE_PATTERNS[$version] = [
                    '^[+-]?\d+$' => NumberType::INTEGER,
                    '^([+-]?(\d+\.\d*|\.\d+)([eE][+-]?\d+)?|[+-]?\.(?:inf|Inf|INF)|\.(?:nan|NaN|NAN))$' => NumberType::FLOAT,
                    '^0x[0-9a-fA-F]+$' => NumberType::HEXADECIMAL,
                    '^0[0-7]+$' => NumberType::OCTAL,
                ];
            } else {
                self::$NUMBER_TYPE_PATTERNS[$version] = [
                    '^[+-]?\d+$' => NumberType::INTEGER,
                    '^([+-]?(\d+\.\d*|\.\d+)([eE][+-]?\d+)?|[+-]?\.(?:inf|Inf|INF)|\.(?:nan|NaN|NAN))$' => NumberType::FLOAT,
                    '^0x[0-9a-fA-F]+$' => NumberType::HEXADECIMAL,
                    '^0o[0-7]+$' => NumberType::OCTAL,
                ];
            }
        }

        foreach (self::$NUMBER_TYPE_PATTERNS[$version] as $pattern => $numberType) {
            if (FormatHelper::matchPattern($pattern, $value)) {
                return $numberType;
            }
        }

        return null;
    }

}
