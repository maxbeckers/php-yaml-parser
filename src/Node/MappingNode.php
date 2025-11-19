<?php

namespace MaxBeckers\YamlParser\Node;

use MaxBeckers\YamlParser\Api\NodeInterface;

final class MappingNode extends AbstractNode
{
    public const TYPE = 'mapping';

    /**
     * @param array<MappingNodeItem> $mappingNodeItems
     */
    public function __construct(
        private array $mappingNodeItems = [],
        NodeMetadata $metadata = new NodeMetadata()
    ) {
        parent::__construct($metadata);
    }

    public function getType(): string
    {
        return self::TYPE;
    }

    public function addMappingItem(MappingNodeItem $item): void
    {
        $this->mappingNodeItems[] = $item;
    }

    public function addPair(NodeInterface $key, NodeInterface $value): void
    {
        $this->mappingNodeItems[] = new MappingNodeItem($key, $value);
    }

    public function getMappingNodeItems(): array
    {
        return $this->mappingNodeItems;
    }
}
