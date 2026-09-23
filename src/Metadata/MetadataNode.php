<?php

namespace MaxBeckers\YamlParser\Metadata;

use MaxBeckers\YamlParser\Node\NodeMetadata;

/**
 * Lightweight metadata tree captured by ScanParser when
 * ParsingConfig::preserveMetadata is enabled. Mirrors the shape of the
 * actually-parsed value tree (scalars, mappings, sequences) without
 * depending on any AST node classes.
 */
final class MetadataNode
{
    private const KIND_SCALAR = 'scalar';
    private const KIND_MAPPING = 'mapping';
    private const KIND_SEQUENCE = 'sequence';

    /**
     * @param self::KIND_* $kind
     * @param array<int|string, array{key: ?NodeMetadata, value: self}>|list<self> $items
     */
    private function __construct(
        private readonly string $kind,
        private readonly array $items,
        private readonly ?NodeMetadata $ownMetadata,
    ) {
    }

    public static function scalar(?NodeMetadata $ownMetadata): self
    {
        return new self(self::KIND_SCALAR, [], $ownMetadata);
    }

    /**
     * @param array<int|string, array{key: ?NodeMetadata, value: self}> $items
     */
    public static function mapping(array $items, ?NodeMetadata $ownMetadata): self
    {
        return new self(self::KIND_MAPPING, $items, $ownMetadata);
    }

    /**
     * @param list<self> $items
     */
    public static function sequence(array $items, ?NodeMetadata $ownMetadata): self
    {
        return new self(self::KIND_SEQUENCE, $items, $ownMetadata);
    }

    public function getOwnMetadata(): ?NodeMetadata
    {
        return $this->ownMetadata;
    }

    public function isMapping(): bool
    {
        return $this->kind === self::KIND_MAPPING;
    }

    public function isSequence(): bool
    {
        return $this->kind === self::KIND_SEQUENCE;
    }

    public function getMappingKeyMetadata(int|string $key): ?NodeMetadata
    {
        $item = $this->items[$key] ?? $this->items[(string) $key] ?? null;

        return is_array($item) ? $item['key'] : null;
    }

    public function getMappingValueNode(int|string $key): ?self
    {
        $item = $this->items[$key] ?? $this->items[(string) $key] ?? null;

        return is_array($item) && $item['value'] instanceof self ? $item['value'] : null;
    }

    public function getSequenceItemNode(int $index): ?self
    {
        $item = $this->items[$index] ?? null;

        return $item instanceof self ? $item : null;
    }

    public function withOwnMetadata(?NodeMetadata $ownMetadata): self
    {
        return new self($this->kind, $this->items, $ownMetadata);
    }
}
