<?php

namespace MaxBeckers\YamlParser\Node;

use MaxBeckers\YamlParser\Api\NodeInterface;

final class SequenceNode extends AbstractNode
{
    /**
     * @param array<NodeInterface> $items
     */
    public function __construct(
        private array $items = [],
        NodeMetadata $metadata = new NodeMetadata()
    ) {
        parent::__construct($metadata);
    }

    public function addItem(NodeInterface $node): void
    {
        $this->items[] = $node;
    }

    public function getItems(): array
    {
        return $this->items;
    }
}
