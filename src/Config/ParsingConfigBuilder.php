<?php

namespace MaxBeckers\YamlParser\Config;

final class ParsingConfigBuilder
{
    private bool $strictMode = true;
    private bool $returnPlainArrays = false;
    private ?int $maxDepth = null;
    private ?int $maxFileSize = null;
    private bool $lazyResolution = false;
    private bool $preserveMetadata = false;

    public static function create(): self
    {
        return new self();
    }

    public function withStrictMode(bool $strictMode): self
    {
        $this->strictMode = $strictMode;

        return $this;
    }

    public function withReturnPlainArrays(bool $returnPlainArrays): self
    {
        $this->returnPlainArrays = $returnPlainArrays;

        return $this;
    }

    public function withMaxDepth(?int $maxDepth): self
    {
        if ($maxDepth !== null && $maxDepth < 1) {
            throw new \InvalidArgumentException('maxDepth must be greater than 0 when provided.');
        }

        $this->maxDepth = $maxDepth;

        return $this;
    }

    public function withMaxFileSize(?int $maxFileSize): self
    {
        if ($maxFileSize !== null && $maxFileSize < 1) {
            throw new \InvalidArgumentException('maxFileSize must be greater than 0 when provided.');
        }

        $this->maxFileSize = $maxFileSize;

        return $this;
    }

    public function withLazyResolution(bool $lazyResolution): self
    {
        $this->lazyResolution = $lazyResolution;

        return $this;
    }

    public function withPreserveMetadata(bool $preserveMetadata): self
    {
        $this->preserveMetadata = $preserveMetadata;

        return $this;
    }

    public function build(): ParsingConfig
    {
        return new ParsingConfig(
            strictMode: $this->strictMode,
            returnPlainArrays: $this->returnPlainArrays,
            maxDepth: $this->maxDepth,
            maxFileSize: $this->maxFileSize,
            lazyResolution: $this->lazyResolution,
            preserveMetadata: $this->preserveMetadata,
        );
    }
}
