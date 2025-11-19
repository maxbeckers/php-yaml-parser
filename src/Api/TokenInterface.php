<?php

namespace MaxBeckers\YamlParser\Api;

use MaxBeckers\YamlParser\Lexer\TokenType;

interface TokenInterface
{
    public function is(TokenType $type): bool;
    public function isOneOf(TokenType ...$types): bool;
    public function isScalar(): bool;
    public function getMetadata(): array;
}
