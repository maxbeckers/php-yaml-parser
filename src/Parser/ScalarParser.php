<?php

namespace MaxBeckers\YamlParser\Parser;

use MaxBeckers\YamlParser\Api\NodeInterface;
use MaxBeckers\YamlParser\Api\TokenParserInterface;
use MaxBeckers\YamlParser\Exception\ParserException;
use MaxBeckers\YamlParser\Lexer\Token;
use MaxBeckers\YamlParser\Node\NodeMetadata;
use MaxBeckers\YamlParser\Node\ScalarNode;
use MaxBeckers\YamlParser\Rule\Format\NumberType;
use MaxBeckers\YamlParser\Rule\FormatHelper;
use MaxBeckers\YamlParser\Rule\Version;

final class ScalarParser implements TokenParserInterface
{
    public static function supports(ParserContext $context): bool
    {
        return Parser::peek($context)->isScalar();
    }

    public static function parse(ParserContext $context, NodeMetadata $metadata = new NodeMetadata(), bool $isKey = false): NodeInterface
    {
        if ($isKey) {
            return self::parseKey($context, $metadata);
        }

        $value = Parser::peek($context)->value;

        if (self::isNullValue($context, $value)) {
            $value = null;
        } elseif (self::isBooleanValue($context, $value)) {
            $lowerValue = strtolower($value);
            if (in_array($lowerValue, ['true', 'yes', 'on'], true)) {
                $value = true;
            } else {
                $value = false;
            }
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

        if ($token->getMetadata()[Token::METADATA_WAS_MULTILINE_INPUT] ?? false) {
            throw new ParserException(
                'Multiline scalars are not allowed as mapping keys.',
                $token
            );
        }

        $value = $token->value;

        if (strlen($value) > 1000) {
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
            return in_array($lowerValue, ['true', 'false', 'yes', 'no', 'on', 'off'], true);
        }

        return in_array($lowerValue, ['true', 'false'], true);
    }

    private static function getNumberType(ParserContext $context, string $value): ?NumberType
    {
        $numberTypes = [
            '^[+-]?\d+$' => NumberType::INTEGER,
            '^([+-]?(\d+\.\d*|\.\d+)([eE][+-]?\d+)?|[+-]?\.(?:inf|Inf|INF)|\.(?:nan|NaN|NAN))$' => NumberType::FLOAT,
            '^0x[0-9a-fA-F]+$' => NumberType::HEXADECIMAL,
        ];
        if ($context->getYamlVersion() === Version::VERSION_1_1) {
            $numberTypes['^0[0-7]+$'] = NumberType::OCTAL;
        } else {
            $numberTypes['^0o[0-7]+$'] = NumberType::OCTAL;
        }

        foreach ($numberTypes as $pattern => $numberType) {
            if (FormatHelper::matchPattern($pattern, $value)) {
                return $numberType;
            }
        }

        return null;
    }

}
