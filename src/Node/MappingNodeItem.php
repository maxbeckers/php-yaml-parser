<?php

namespace MaxBeckers\YamlParser\Node;

use MaxBeckers\YamlParser\Api\NodeInterface;

final readonly class MappingNodeItem
{
    public function __construct(
        private NodeInterface $key,
        private NodeInterface $value
    ) {
    }

    public function getKey(): NodeInterface
    {
        return $this->key;
    }

    public function getValue(): NodeInterface
    {
        return $this->value;
    }

    public function getKeySerialized(): string
    {
        $normalized = $this->nodeToArray($this->key);

        return !is_scalar($normalized) ? json_encode($normalized) : $normalized;
    }

    private function nodeToArray(NodeInterface $node): mixed
    {
        if ($node instanceof ScalarNode) {
            return $node->getValue();
        }
        if ($node instanceof SequenceNode) {
            return array_map(fn ($item) => $this->nodeToArray($item), $node->getItems());
        }
        if ($node instanceof MappingNode) {
            $result = [];
            foreach ($node->getMappingNodeItems() as $value) {
                $key = $this->nodeToArray($value->getKey());
                $result[!is_scalar($key) ? json_encode($key) : $key] = $this->nodeToArray($value->getValue());
            }

            return $result;
        }

        return null;
    }
}
