<?php

namespace MaxBeckers\YamlParser\Api;

interface NodeMetadataInterface
{
    public function getTag(mixed $default = null): ?string;

    public function setTag(?string $tag): void;

    public function getAnchor(mixed $default = null): ?string;

    public function setAnchor(?string $anchor): void;

    public function getAlias(mixed $default = null): ?string;

    public function setAlias(?string $alias): void;

    public function isMergeKey(): bool;

    public function setIsMergeKey(): void;
}
