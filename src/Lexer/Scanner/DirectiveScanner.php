<?php

namespace MaxBeckers\YamlParser\Lexer\Scanner;

use MaxBeckers\YamlParser\Exception\LexerException;
use MaxBeckers\YamlParser\Lexer\ContextMode;
use MaxBeckers\YamlParser\Lexer\LexerContext;
use MaxBeckers\YamlParser\Lexer\TokenType;
use MaxBeckers\YamlParser\Rule\Document\DirectiveType;
use MaxBeckers\YamlParser\Rule\Version;

final class DirectiveScanner extends AbstractScanner
{
    public static function scan(LexerContext $context, string $currentChar): bool
    {
        if ($currentChar !== '%' || $context->getMode() !== ContextMode::STREAM_START) {
            return false;
        }

        $charsTillEol = $context->getNumberOfCharsTill("\n\r");
        $directive = $context->getInputPart($charsTillEol);
        $spacePos = strcspn($directive, ' ');
        if ($spacePos === 0) {
            return false;
        }
        $directiveName = substr($directive, 1, $spacePos - 1);
        $directiveValue = trim(substr($directive, $spacePos));
        $directiveType = DirectiveType::tryFrom($directiveName);

        if ($directiveType === null) {
            throw new LexerException(
                "Invalid directive name '{$directiveName}' in line {$context->getLine()}, column {$context->getColumn()}"
            );
        }

        switch ($directiveType) {
            case DirectiveType::YAML:
                self::checkYamlDirective($context, $directiveValue);
                $context->setYamlVersion(Version::from($directiveValue));
                break;
            case DirectiveType::TAG:
                self::checkTagDirective($context, $directiveValue);
                break;
        }

        $token = static::createToken($context, TokenType::DIRECTIVE, ['type' => $directiveType, 'value' => $directiveValue]);
        $context->addDirectiveToken($token);
        $context->increasePosition($charsTillEol);

        return true;
    }

    private static function checkYamlDirective(LexerContext $context, string $directiveValue): void
    {
        foreach ($context->getDirectiveTokens(1) as $token) {
            if ($token->value['type'] === DirectiveType::YAML) {
                throw new LexerException(
                    "YAML directive already defined earlier in line {$context->getLine()}, column {$context->getColumn()}"
                );
            }
        }
        if (empty($directiveValue)) {
            throw new LexerException(
                "Missing directive value for 'YAML' directive in line {$context->getLine()}, column {$context->getColumn()}"
            );
        }
        $versionParts = explode('.', $directiveValue);
        if (count($versionParts) !== 2) {
            throw new LexerException(
                "Invalid YAML directive value '{$directiveValue}' in line {$context->getLine()}, column {$context->getColumn()}"
            );
        }

        if (!preg_match('/^\d+$/', $versionParts[0]) || !preg_match('/^\d+$/', $versionParts[1])) {
            throw new LexerException(
                "YAML directive parameters must be integers (major and minor version) in line {$context->getLine()}, column {$context->getColumn()}"
            );
        }

        $version = Version::tryFrom($versionParts[0] . '.' . $versionParts[1]);
        if ($version === null) {
            throw new LexerException(
                "Unsupported YAML version '{$directiveValue}' in line {$context->getLine()}, column {$context->getColumn()}"
            );
        }
    }

    private static function checkTagDirective(LexerContext $context, string $directiveValue): void
    {
        foreach ($context->getDirectiveTokens(1) as $token) {
            if ($token->value['type'] === DirectiveType::TAG) {
                $existingParams = $token->value['value'];
                $existingParts = preg_split('/\s+/', $existingParams);
                $newParts = preg_split('/\s+/', $directiveValue);
                if (count($existingParts) >= 1 && count($newParts) >= 1 && $existingParts[0] === $newParts[0]) {
                    throw new LexerException(
                        "TAG directive with handle '{$existingParts[0]}' already defined earlier in line {$context->getLine()}, column {$context->getColumn()}"
                    );
                }
            }
        }
    }
}
