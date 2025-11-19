<?php

namespace MaxBeckers\YamlParser\Lexer;

final class TokenStream
{
    private int $position = 0;

    public function __construct(
        private readonly array $tokens
    ) {
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

        return $token;
    }

    /**
     * Peek at token without advancing.
     */
    public function peek(int $offset = 1): ?Token
    {
        return $this->tokens[$this->position + $offset] ?? null;
    }

    /**
     * Check if at end.
     */
    public function isAtEnd(): bool
    {
        return $this->position >= count($this->tokens) || $this->current() === null || $this->current()->is(TokenType::EOF);
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
        $this->position = max(0, min($position, count($this->tokens)));
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
        return array_slice($this->tokens, $this->position);
    }

    /**
     * Get all tokens.
     */
    public function all(): array
    {
        return $this->tokens;
    }

    /**
     * Count total tokens.
     */
    public function count(): int
    {
        return count($this->tokens);
    }

    /**
     * Expect current token to be of type.
     */
    public function expect(TokenType $type): Token
    {
        $token = $this->current();

        if ($token === null) {
            throw new YamlLexerException(
                "Expected {$type->value} but reached end of stream"
            );
        }

        if (!$token->is($type)) {
            throw new YamlLexerException(
                "Expected {$type->value} but got {$token->type->value}",
                null,
                $token->line,
                $token->column
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
