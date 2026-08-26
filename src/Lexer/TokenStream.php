<?php

namespace MaxBeckers\YamlParser\Lexer;

use MaxBeckers\YamlParser\Exception\LexerException;

final class TokenStream
{
    private int $position = 0;
    private int $tokenCount;

    public function __construct(
        private array $tokens,
        private readonly bool $releaseConsumedTokens = true,
    ) {
        $this->tokenCount = count($tokens);
    }

    /**
     * Get current token.
     */
    public function current(): ?Token
    {
        return $this->peek(0) ?? null;
    }

    /**
     * Get next token and advance.
     */
    public function next(): ?Token
    {
        $token = $this->current();
        $this->position++;

        if ($this->releaseConsumedTokens) {
            $dropIndex = $this->position - 2;
            if ($dropIndex >= 0 && array_key_exists($dropIndex, $this->tokens)) {
                $this->tokens[$dropIndex] = null;
            }
        }

        return $token;
    }

    /**
     * Peek at token without advancing.
     */
    public function peek(int $offset = 1): ?Token
    {
        $index = $this->position + $offset;
        $token = $this->tokens[$index] ?? null;

        return $token instanceof Token ? $token : null;
    }

    /**
     * Check if at end.
     */
    public function isAtEnd(): bool
    {
        return $this->position >= $this->tokenCount || $this->current() === null || $this->current()->is(TokenType::EOF);
    }

    /**
     * Get current position.
     */
    public function getPosition(): int
    {
        return $this->position;
    }

    /**
     * Set position.
     */
    public function setPosition(int $position): void
    {
        $this->position = max(0, min($position, $this->tokenCount));
    }

    /**
     * Reset to beginning.
     */
    public function reset(): void
    {
        $this->position = 0;
    }

    /**
     * Get all remaining tokens.
     */
    public function remaining(): array
    {
        return array_values(array_filter(
            array_slice($this->tokens, $this->position),
            static fn (mixed $token): bool => $token instanceof Token
        ));
    }

    /**
     * Get all tokens.
     */
    public function all(): array
    {
        return array_values(array_filter($this->tokens, static fn (mixed $token): bool => $token instanceof Token));
    }

    /**
     * Count total tokens.
     */
    public function count(): int
    {
        return $this->tokenCount;
    }

    /**
     * Expect current token to be of type.
     */
    public function expect(TokenType $type): Token
    {
        $token = $this->current();

        if ($token === null) {
            throw new LexerException(
                "Expected {$type->value} but reached end of stream"
            );
        }

        if (!$token->is($type)) {
            throw new LexerException(
                "Expected {$type->value} but got {$token->type->value}",
            );
        }

        return $token;
    }

    /**
     * Consume token if it matches type.
     */
    public function consume(TokenType $type): ?Token
    {
        $token = $this->current();

        if ($token && $token->is($type)) {
            $this->position++;

            return $token;
        }

        return null;
    }

    /**
     * Skip tokens of given type(s).
     */
    public function skip(TokenType ...$types): void
    {
        while ($token = $this->current()) {
            if (!$token->isOneOf(...$types)) {
                break;
            }
            $this->position++;
        }
    }
}
