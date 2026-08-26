<?php

namespace MaxBeckers\YamlParser\Metadata;

use MaxBeckers\YamlParser\Node\NodeMetadata;

final readonly class ValueWithMetadata
{
    public function __construct(
        private mixed $value,
        private ?NodeMetadata $metadata = null,
    ) {
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function getMetadata(): ?NodeMetadata
    {
        return $this->metadata;
    }

    public function hasMetadata(): bool
    {
        return $this->metadata !== null;
    }
}
