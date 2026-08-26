<?php

namespace MaxBeckers\YamlParser\Parser;

use MaxBeckers\YamlParser\Exception\ParserException;
use MaxBeckers\YamlParser\Lexer\TokenStream;
use MaxBeckers\YamlParser\Api\TokenInterface;
use MaxBeckers\YamlParser\Format\Version;

final class ParserContext
{
    public function __construct(
        private readonly TokenStream $stream,
        private int $indentationLevel = 0,
        private Version $yamlVersion = Version::VERSION_1_2,
        private bool $isExplicitKey = false,
        private int $flowDepth = 0,
        private bool $strictMode = true,
        private ?int $maxDepth = null,
        private bool $preserveMetadata = false,
        private int $currentDepth = 0,
    ) {
    }

    public function getStream(): TokenStream
    {
        return $this->stream;
    }

    public function getIndentationLevel(): int
    {
        return $this->indentationLevel;
    }

    public function increaseIndentationLevel(): void
    {
        $this->indentationLevel++;
    }

    public function decreaseIndentationLevel(): void
    {
        $this->indentationLevel--;
    }

    public function setYamlVersion(Version $yamlVersion): void
    {
        $this->yamlVersion = $yamlVersion;
    }

    public function getYamlVersion(): Version
    {
        return $this->yamlVersion;
    }

    public function isExplicitKey(): bool
    {
        return $this->isExplicitKey;
    }

    public function setIsExplicitKey(bool $isExplicitKey): void
    {
        $this->isExplicitKey = $isExplicitKey;
    }

    public function isFlowContext(): bool
    {
        return $this->flowDepth > 0;
    }

    public function enterFlowContext(): void
    {
        $this->flowDepth++;
    }

    public function exitFlowContext(): void
    {
        $this->flowDepth--;
    }

    public function isStrictMode(): bool
    {
        return $this->strictMode;
    }

    public function shouldPreserveMetadata(): bool
    {
        return $this->preserveMetadata;
    }

    public function enterNodeDepth(TokenInterface $token): void
    {
        $this->currentDepth++;

        if ($this->maxDepth !== null && $this->currentDepth > $this->maxDepth) {
            throw new ParserException(
                "Maximum parsing depth of {$this->maxDepth} exceeded",
                $token
            );
        }
    }

    public function exitNodeDepth(): void
    {
        $this->currentDepth--;
    }
}
