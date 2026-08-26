<?php

namespace MaxBeckers\YamlParser\Node;

use MaxBeckers\YamlParser\Api\NodeMetadataInterface;

final class NodeMetadata implements NodeMetadataInterface
{
    public function __construct(
        protected readonly ?string $tag = null,
        protected readonly ?string $anchor = null,
        protected readonly ?string $alias = null,
        protected readonly bool $isMergeKey = false,
        protected readonly ?int $line = null,
        protected readonly ?int $column = null,
        protected readonly array $customMetadata = [],
    ) {
    }

    public function getTag(mixed $default = null): ?string
    {
        return $this->tag ?? $default;
    }

    public function withTag(?string $tag): self
    {
        return new self(
            tag: $tag,
            anchor: $this->anchor,
            alias: $this->alias,
            isMergeKey: $this->isMergeKey,
            line: $this->line,
            column: $this->column,
            customMetadata: $this->customMetadata,
        );
    }

    public function getAnchor(mixed $default = null): ?string
    {
        return $this->anchor ?? $default;
    }

    public function withAnchor(?string $anchor): self
    {
        return new self(
            tag: $this->tag,
            anchor: $anchor,
            alias: $this->alias,
            isMergeKey: $this->isMergeKey,
            line: $this->line,
            column: $this->column,
            customMetadata: $this->customMetadata,
        );
    }

    public function getAlias(mixed $default = null): ?string
    {
        return $this->alias ?? $default;
    }

    public function withAlias(?string $alias): self
    {
        return new self(
            tag: $this->tag,
            anchor: $this->anchor,
            alias: $alias,
            isMergeKey: $this->isMergeKey,
            line: $this->line,
            column: $this->column,
            customMetadata: $this->customMetadata,
        );
    }

    public function isMergeKey(): bool
    {
        return $this->isMergeKey;
    }

    public function withIsMergeKey(bool $isMergeKey = true): self
    {
        return new self(
            tag: $this->tag,
            anchor: $this->anchor,
            alias: $this->alias,
            isMergeKey: $isMergeKey,
            line: $this->line,
            column: $this->column,
            customMetadata: $this->customMetadata,
        );
    }

    public function withPosition(?int $line, ?int $column): self
    {
        return new self(
            tag: $this->tag,
            anchor: $this->anchor,
            alias: $this->alias,
            isMergeKey: $this->isMergeKey,
            line: $line,
            column: $column,
            customMetadata: $this->customMetadata,
        );
    }

    public function getLine(): ?int
    {
        return $this->line;
    }

    public function getColumn(): ?int
    {
        return $this->column;
    }

    public function getCustomMetadata(string $key, mixed $default = null): mixed
    {
        return $this->customMetadata[$key] ?? $default;
    }

    public function hasCustomMetadata(string $key): bool
    {
        return array_key_exists($key, $this->customMetadata);
    }

    public function withCustomMetadata(string $key, mixed $value): self
    {
        $customMetadata = $this->customMetadata;
        $customMetadata[$key] = $value;

        return new self(
            tag: $this->tag,
            anchor: $this->anchor,
            alias: $this->alias,
            isMergeKey: $this->isMergeKey,
            line: $this->line,
            column: $this->column,
            customMetadata: $customMetadata,
        );
    }

    public function getCustomMetadataMap(): array
    {
        return $this->customMetadata;
    }
}
