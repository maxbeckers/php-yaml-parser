<?php

namespace MaxBeckers\YamlParser\Metadata;

use MaxBeckers\YamlParser\Node\NodeMetadata;

final class MetadataProvider
{
    /** @var \SplObjectStorage<\ArrayObject, MetadataNode|false>|null */
    private ?\SplObjectStorage $anchoredNodes = null;
    /**
     * @param list<MetadataNode> $documentMetadataNodes one metadata tree per parsed document
     */
    public function __construct(
        private readonly array $documentMetadataNodes,
        private readonly bool $stripWrapperOnSingleItem = true,
        private readonly mixed $parsedValue = null,
    ) {
    }

    public function getMetadata(array|string|int|null $path = []): ?NodeMetadata
    {
        return $this->resolveNode($this->normalizePath($path))?->getOwnMetadata();
    }

    /**
     * Return the syntactic kind of a node, or null when it is unavailable.
     *
     * Alias nodes are reported as unknown rather than as scalars: their
     * resolved values may be collections and their metadata has no child tree.
     *
     * @return 'mapping'|'sequence'|'scalar'|null
     */
    public function getNodeKind(array|string|int|null $path = []): ?string
    {
        $node = $this->resolveNode($this->normalizePath($path));
        if ($node === null || $node->getOwnMetadata()?->getAlias() !== null) {
            return null;
        }
        if ($node->isMapping()) {
            return 'mapping';
        }
        if ($node->isSequence()) {
            return 'sequence';
        }

        return $node->getOwnMetadata() === null ? null : 'scalar';
    }

    /**
     * Return the resolved value's kind when collection aliases retain object identity.
     * Alias descendants have no syntactic source metadata at their alias location.
     *
     * @return 'mapping'|'sequence'|'scalar'|null
     */
    public function getResolvedNodeKind(array|string|int|null $path = []): ?string
    {
        if ($this->anchoredNodes === null) {
            $this->anchoredNodes = new \SplObjectStorage();
            $this->indexAnchors($this->parsedValue, $this->getRootNodeForPublicValue());
        }
        $node = $this->getRootNodeForPublicValue();
        $value = $this->parsedValue;
        foreach ($this->normalizePath($path) as $segment) {
            $node = $this->resolvedNode($node, $value);
            if ($node === null || (!is_array($value) && !$value instanceof \ArrayObject)) {
                return null;
            }
            $items = $value instanceof \ArrayObject ? $value->getArrayCopy() : $value;
            if (!array_key_exists($segment, $items)) {
                return null;
            }
            $node = $node->isMapping() ? $node->getMappingValueNode($segment)
                : ($node->isSequence() && $this->toIndex($segment) !== null ? $node->getSequenceItemNode($this->toIndex($segment)) : null);
            $value = $items[$segment];
        }
        if ($node?->getOwnMetadata()?->getAlias() !== null && !is_array($value) && !$value instanceof \ArrayObject) {
            return 'scalar';
        }
        $node = $this->resolvedNode($node, $value);
        if ($node === null) {
            return null;
        }

        return $node->isMapping() ? 'mapping' : ($node->isSequence() ? 'sequence' : ($node->getOwnMetadata() !== null ? 'scalar' : null));
    }

    private function resolvedNode(?MetadataNode $node, mixed $value): ?MetadataNode
    {
        if ($node === null) {
            return null;
        }
        if ($value instanceof \ArrayObject && $this->anchoredNodes !== null
            && $this->anchoredNodes->contains($value) && $this->anchoredNodes[$value] === false) {
            return null;
        }
        if ($node->getOwnMetadata()?->getAlias() === null) {
            return $node;
        }
        if (!$value instanceof \ArrayObject || $this->anchoredNodes === null || !$this->anchoredNodes->contains($value)) {
            return null;
        }
        $resolved = $this->anchoredNodes[$value];

        return $resolved instanceof MetadataNode ? $resolved : null;
    }

    private function indexAnchors(mixed $value, ?MetadataNode $node): void
    {
        if ($node === null || $node->getOwnMetadata()?->getAlias() !== null || (!is_array($value) && !$value instanceof \ArrayObject)) {
            return;
        }
        if ($value instanceof \ArrayObject && $node->getOwnMetadata()?->getAnchor() !== null) {
            // Reused collection anchors can resolve to the same object with different syntactic trees.
            if ($this->anchoredNodes->contains($value)) {
                $this->anchoredNodes[$value] = false;
            } else {
                $this->anchoredNodes[$value] = $node;
            }
        }
        foreach ($value as $key => $child) {
            $childNode = $node->isMapping() ? $node->getMappingValueNode($key)
                : ($node->isSequence() && is_int($key) ? $node->getSequenceItemNode($key) : null);
            $this->indexAnchors($child, $childNode);
        }
    }

    public function getKeyMetadata(array|string|int|null $path): ?NodeMetadata
    {
        $normalizedPath = $this->normalizePath($path);
        if ($normalizedPath === []) {
            return null;
        }

        $key = array_pop($normalizedPath);
        $parentNode = $this->resolveNode($normalizedPath);

        if ($parentNode === null || !$parentNode->isMapping()) {
            return null;
        }

        return $parentNode->getMappingKeyMetadata($key);
    }

    public function hasMetadata(array|string|int|null $path = []): bool
    {
        return $this->getMetadata($path) !== null;
    }

    public function getValueWithMetadata(mixed $value, array|string|int|null $path = []): ValueWithMetadata
    {
        $normalizedPath = $this->normalizePath($path);

        return new ValueWithMetadata(
            $this->extractValue($value, $normalizedPath),
            $this->getMetadata($normalizedPath),
        );
    }

    /**
     * @param array<int, int|string> $path
     */
    private function resolveNode(array $path): ?MetadataNode
    {
        $current = $this->getRootNodeForPublicValue();

        foreach ($path as $segment) {
            if ($current === null) {
                return null;
            }

            if ($current->isSequence()) {
                $index = $this->toIndex($segment);
                if ($index === null) {
                    return null;
                }

                $current = $current->getSequenceItemNode($index);
                continue;
            }

            if ($current->isMapping()) {
                $current = $current->getMappingValueNode($segment);
                continue;
            }

            return null;
        }

        return $current;
    }

    private function getRootNodeForPublicValue(): ?MetadataNode
    {
        if ($this->stripWrapperOnSingleItem && count($this->documentMetadataNodes) === 1) {
            return $this->documentMetadataNodes[0] ?? null;
        }

        return MetadataNode::sequence($this->documentMetadataNodes, null);
    }

    /**
     * @param array<int, int|string> $path
     */
    private function extractValue(mixed $value, array $path): mixed
    {
        $current = $value;

        foreach ($path as $segment) {
            if ($current instanceof \ArrayObject) {
                $arrayCopy = $current->getArrayCopy();
                if (array_key_exists($segment, $arrayCopy)) {
                    $current = $arrayCopy[$segment];
                    continue;
                }

                $stringSegment = (string) $segment;
                if (!array_key_exists($stringSegment, $arrayCopy)) {
                    return null;
                }

                $current = $arrayCopy[$stringSegment];
                continue;
            }

            if (is_array($current)) {
                if (array_key_exists($segment, $current)) {
                    $current = $current[$segment];
                    continue;
                }

                $stringSegment = (string) $segment;
                if (!array_key_exists($stringSegment, $current)) {
                    return null;
                }

                $current = $current[$stringSegment];
                continue;
            }

            return null;
        }

        return $current;
    }

    /**
     * @return array<int, int|string>
     */
    private function normalizePath(array|string|int|null $path): array
    {
        if ($path === null || $path === '') {
            return [];
        }

        if (is_int($path)) {
            return [$path];
        }

        if (is_string($path)) {
            return array_map(
                static fn (string $segment): int|string => ctype_digit($segment) ? (int) $segment : $segment,
                array_values(array_filter(explode('.', $path), static fn (string $segment): bool => $segment !== '')),
            );
        }

        return array_map(
            static fn (mixed $segment): int|string => is_int($segment) ? $segment : (string) $segment,
            $path,
        );
    }

    private function toIndex(int|string $segment): ?int
    {
        if (is_int($segment)) {
            return $segment;
        }

        return ctype_digit($segment) ? (int) $segment : null;
    }
}
