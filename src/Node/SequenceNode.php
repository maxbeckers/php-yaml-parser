<?php

namespace MaxBeckers\YamlParser\Node;

use MaxBeckers\YamlParser\Api\NodeInterface;

final class SequenceNode extends AbstractNode
{
    public const TYPE = 'sequence';

    /**
     * @param array<NodeInterface> $items
     */
    public function __construct(
        private array $items = [],
        NodeMetadata $metadata = new NodeMetadata()
    ) {
        parent::__construct($metadata);
    }

    public function getType(): string
    {
        return self::TYPE;
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
