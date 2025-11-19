<?php

namespace MaxBeckers\YamlParser\Lexer;

use MaxBeckers\YamlParser\Api\TokenInterface;

final readonly class Token implements TokenInterface
{
    public const METADATA_VERSION = 'version';
    public const METADATA_WAS_MULTILINE_INPUT = 'was_multiline_input';

    public function __construct(
        public TokenType $type,
        public mixed $value = null,
        public int $line = 0,
        public int $column = 0,
        public array $metadata = []
    ) {
    }

    public function is(TokenType $type): bool
    {
        return $this->type === $type;
    }

    public function isOneOf(TokenType ...$types): bool
    {
        return in_array($this->type, $types, true);
    }

    public function isScalar(): bool
    {
        return $this->isOneOf(
            TokenType::PLAIN_SCALAR,
            TokenType::SINGLE_QUOTED_SCALAR,
            TokenType::DOUBLE_QUOTED_SCALAR,
            TokenType::LITERAL_SCALAR,
            TokenType::FOLDED_SCALAR
        );
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }
}
