<?php

namespace MaxBeckers\YamlParser\Node;

use MaxBeckers\YamlParser\Api\NodeMetadataInterface;

final class NodeMetadata implements NodeMetadataInterface
{
    public function __construct(
        protected ?string $tag = null,
        protected ?string $anchor = null,
        protected ?string $alias = null,
        protected bool $isMergeKey = false
    ) {
    }

    public function getTag(mixed $default = null): ?string
    {
        return $this->tag ?? $default;
    }

    public function setTag(?string $tag): void
    {
        $this->tag = $tag;
    }

    public function getAnchor(mixed $default = null): ?string
    {
        return $this->anchor ?? $default;
    }

    public function setAnchor(?string $anchor): void
    {
        $this->anchor = $anchor;
    }

    public function getAlias(mixed $default = null): ?string
    {
        return $this->alias ?? $default;
    }

    public function setAlias(?string $alias): void
    {
        $this->alias = $alias;
    }

    public function isMergeKey(): bool
    {
        return $this->isMergeKey;
    }

    public function setIsMergeKey(): void
    {
        $this->isMergeKey = true;
    }
}
