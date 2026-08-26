<?php

namespace MaxBeckers\YamlParser\Metadata;

use MaxBeckers\YamlParser\Api\NodeInterface;
use MaxBeckers\YamlParser\Node\DocumentNode;
use MaxBeckers\YamlParser\Node\MappingNode;
use MaxBeckers\YamlParser\Node\MappingNodeItem;
use MaxBeckers\YamlParser\Node\NodeMetadata;
use MaxBeckers\YamlParser\Node\SequenceNode;
use MaxBeckers\YamlParser\Node\YamlNode;

final readonly class MetadataProvider
{
    public function __construct(
        private NodeInterface $ast,
        private bool $stripWrapperOnSingleItem = true,
    ) {
    }

    public function getMetadata(array|string|int|null $path = []): ?NodeMetadata
    {
        return $this->resolveNode($this->normalizePath($path))?->getMetadata();
    }

    public function getKeyMetadata(array|string|int|null $path): ?NodeMetadata
    {
        $normalizedPath = $this->normalizePath($path);
        if ($normalizedPath === []) {
            return null;
        }

        $key = array_pop($normalizedPath);
        $parentNode = $this->resolveNode($normalizedPath);

        if (!$parentNode instanceof MappingNode) {
            return null;
        }

        $mappingItem = $this->findMappingItemByKey($parentNode, $key);

        return $mappingItem?->getKey()->getMetadata();
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
    private function resolveNode(array $path): ?NodeInterface
    {
        $current = $this->getRootNodeForPublicValue();

        foreach ($path as $segment) {
            if ($current instanceof DocumentNode) {
                $current = $current->getRoot();
            }

            if ($current instanceof YamlNode) {
                $index = $this->toIndex($segment);
                if ($index === null) {
                    return null;
                }

                $current = $current->getDocuments()[$index] ?? null;
                continue;
            }

            if ($current instanceof SequenceNode) {
                $index = $this->toIndex($segment);
                if ($index === null) {
                    return null;
                }

                $current = $current->getItems()[$index] ?? null;
                continue;
            }

            if ($current instanceof MappingNode) {
                $mappingItem = $this->findMappingItemByKey($current, $segment);
                $current = $mappingItem?->getValue();
                continue;
            }

            return null;
        }

        if ($current instanceof DocumentNode) {
            return $current->getRoot();
        }

        return $current;
    }

    private function getRootNodeForPublicValue(): NodeInterface
    {
        if (!$this->stripWrapperOnSingleItem) {
            return $this->ast;
        }

        if ($this->ast instanceof YamlNode) {
            $documents = $this->ast->getDocuments();
            if (count($documents) !== 1) {
                return $this->ast;
            }

            $document = $documents[0];

            return $document instanceof DocumentNode ? $document->getRoot() : $document;
        }

        if ($this->ast instanceof SequenceNode && $this->isDocumentStreamSequence($this->ast)) {
            $documents = $this->ast->getItems();
            if (count($documents) !== 1) {
                return $this->ast;
            }

            $document = $documents[0];

            return $document instanceof DocumentNode ? $document->getRoot() : $document;
        }

        return $this->ast;
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

    private function isDocumentStreamSequence(SequenceNode $sequenceNode): bool
    {
        foreach ($sequenceNode->getItems() as $item) {
            if (!$item instanceof DocumentNode) {
                return false;
            }
        }

        return true;
    }

    private function findMappingItemByKey(MappingNode $mappingNode, int|string $key): ?MappingNodeItem
    {
        $normalizedKey = (string) $key;

        foreach ($mappingNode->getMappingNodeItems() as $item) {
            if ($item->getKeySerialized() === $normalizedKey) {
                return $item;
            }
        }

        return null;
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
