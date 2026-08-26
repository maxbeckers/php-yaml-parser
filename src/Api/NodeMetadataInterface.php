<?php

namespace MaxBeckers\YamlParser\Api;

interface NodeMetadataInterface
{
    public function getTag(mixed $default = null): ?string;

    public function withTag(?string $tag): self;

    public function getAnchor(mixed $default = null): ?string;

    public function withAnchor(?string $anchor): self;

    public function getAlias(mixed $default = null): ?string;

    public function withAlias(?string $alias): self;

    public function isMergeKey(): bool;

    public function withIsMergeKey(bool $isMergeKey = true): self;

    public function getLine(): ?int;

    public function getColumn(): ?int;

    public function withPosition(?int $line, ?int $column): self;

    public function getCustomMetadata(string $key, mixed $default = null): mixed;

    public function hasCustomMetadata(string $key): bool;

    public function withCustomMetadata(string $key, mixed $value): self;

    /**
     * @return array<string, mixed>
     */
    public function getCustomMetadataMap(): array;
}
