<?php

namespace MaxBeckers\YamlParser\Lexer;

use MaxBeckers\YamlParser\Api\TokenInterface;
use MaxBeckers\YamlParser\Rule\Version;

final class LexerContext
{
    private int $inputLength;
    private int $position = 0;
    private int $line = 1;
    private int $column = 0;
    private int $document = 0;
    /** @var TokenInterface[] */
    private array $tokens = [];
    /** @var array<int, TokenInterface[]> */
    private array $directiveTokensByDocument = [];
    /** @var array<int, Version> */
    private array $yamlVersionByDocument = [];
    private array $modeStack = [ContextMode::STREAM_START];
    /** @var int[] */
    private array $indentStack = [-1];
    private array $flowDepthStack = [0];

    public function __construct(
        private readonly string $input,
    ) {
        $this->inputLength = strlen($input);
    }

    public function getInputLength(): int
    {
        return $this->inputLength;
    }

    public function getInputPart(int $length, int $offset = 0): string
    {
        if ($this->getPosition() + $offset >= 0) {
            return substr($this->input, $this->getPosition() + $offset, $length);
        }

        return '';

    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function increasePositionInLine(int $amount = 1): void
    {
        $this->position += $amount;
        $this->column += $amount;
    }

    public function increasePosition(int $amount = 1): void
    {
        $this->position += $amount;
    }

    public function getLine(): int
    {
        return $this->line;
    }

    public function increaseLine(): void
    {
        $this->line++;
        $this->column = 0;
    }

    public function setColumn(int $column): void
    {
        $this->column = $column;
    }

    public function getColumn(): int
    {
        return $this->column;
    }

    public function getTokens(): array
    {
        return $this->tokens;
    }

    public function getLastToken(): TokenInterface
    {
        return end($this->tokens);
    }

    public function addToken(TokenInterface $token): void
    {
        $this->tokens[] = $token;
    }

    public function addDirectiveToken(TokenInterface $token): void
    {
        if (!isset($this->directiveTokensByDocument[$this->document + 1])) {
            $this->directiveTokensByDocument[$this->document + 1] = [];
        }

        $this->directiveTokensByDocument[$this->document + 1][] = $token;
    }

    public function getDirectiveTokens(int $offset = 0): array
    {
        if (!isset($this->directiveTokensByDocument[$this->document + $offset])) {
            $this->directiveTokensByDocument[$this->document + $offset] = [];
        }

        return $this->directiveTokensByDocument[$this->document + $offset];
    }

    public function setYamlVersion(Version $version): void
    {
        $this->yamlVersionByDocument[$this->document + 1] = $version;
    }

    public function getYamlVersion(int $offset = 0): ?Version
    {
        return $this->yamlVersionByDocument[$this->document + $offset] ?? null;
    }

    public function isAtEndOfFile(int $offset = 0): bool
    {
        return $this->getPosition() + $offset >= $this->getInputLength();
    }

    public function getNumberOfCharsTill(string $till, int $skip = 0): int
    {
        return strcspn($this->input, $till, $this->getPosition() + $skip);
    }

    public function getNumberOfCharsCount(string $including, int $skip = 0): int
    {
        return strspn($this->input, $including, $this->getPosition() + $skip);
    }

    public function startNewDocument(): void
    {
        $this->document++;
    }

    public function getMode(): ContextMode
    {
        return end($this->modeStack);
    }

    public function pushMode(ContextMode $mode): void
    {
        $this->modeStack[] = $mode;
    }

    public function popMode(): ContextMode
    {
        if (count($this->modeStack) > 1) {
            return array_pop($this->modeStack);
        }

        return $this->modeStack[0];
    }

    public function getCurrentIndent(): int
    {
        return end($this->indentStack);
    }

    public function pushIndent(int $indent): void
    {
        if (end($this->indentStack) !== $indent) {
            $this->indentStack[] = $indent;
        }
    }

    public function popIndent(): int
    {
        if (count($this->indentStack) > 1) {
            return array_pop($this->indentStack);
        }

        return -1;
    }

    public function isInFlow(): bool
    {
        return end($this->flowDepthStack) > 0;
    }

    public function enterFlow(): void
    {
        $current = end($this->flowDepthStack);
        $this->flowDepthStack[] = $current + 1;
    }

    public function exitFlow(): void
    {
        if (count($this->flowDepthStack) > 1) {
            array_pop($this->flowDepthStack);
        }
    }
}
