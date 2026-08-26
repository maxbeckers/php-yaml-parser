<?php

namespace MaxBeckers\YamlParser\Config;

final readonly class ParsingConfig
{
    public function __construct(
        public bool $strictMode = true,
        public bool $returnPlainArrays = false,
        public ?int $maxDepth = null,
        public ?int $maxFileSize = null,
        public bool $lazyResolution = false,
        public bool $preserveMetadata = false,
        public bool $releaseConsumedTokens = true,
    ) {
        if ($this->maxDepth !== null && $this->maxDepth < 1) {
            throw new \InvalidArgumentException('maxDepth must be greater than 0 when provided.');
        }

        if ($this->maxFileSize !== null && $this->maxFileSize < 1) {
            throw new \InvalidArgumentException('maxFileSize must be greater than 0 when provided.');
        }
    }
}
