<?php

namespace MaxBeckers\YamlParser\Resolver\Tag;

use MaxBeckers\YamlParser\Api\NodeInterface;
use MaxBeckers\YamlParser\Node\DocumentNode;
use MaxBeckers\YamlParser\Node\MappingNode;
use MaxBeckers\YamlParser\Node\ScalarNode;
use MaxBeckers\YamlParser\Node\SequenceNode;

final readonly class TagProcessor
{
    public function __construct(
        private TagRegistry $registry
    ) {
    }

    public function process(NodeInterface $node): NodeInterface
    {
        return $this->processNode($node);
    }

    private function processNode(NodeInterface $node): NodeInterface
    {
        if ($tag = $node->getMetadata()->getTag()) {
            $handler = $this->registry->getHandler($tag);

            if ($handler) {
                $value = $this->extractValue($node);
                $processedValue = $handler->handle($value, $node->getMetadata());

                return new ScalarNode($processedValue, $node->getMetadata());
            }
        }

        return match (true) {
            $node instanceof SequenceNode => $this->processSequence($node),
            $node instanceof MappingNode => $this->processMapping($node),
            $node instanceof DocumentNode => new DocumentNode($this->processNode($node->getRoot())),
            default => $node,
        };
    }

    private function processSequence(SequenceNode $node): SequenceNode
    {
        $processed = new SequenceNode([], $node->getMetadata());

        foreach ($node->getItems() as $item) {
            $processed->addItem($this->processNode($item));
        }

        return $processed;
    }

    private function processMapping(MappingNode $node): MappingNode
    {
        $processed = new MappingNode([], $node->getMetadata());

        foreach ($node->getMappingNodeItems() as $value) {
            $processed->addMappingItem($value);
        }

        return $processed;
    }

    private function extractValue(NodeInterface $node): mixed
    {
        return match (true) {
            $node instanceof ScalarNode => $node->getValue(),
            $node instanceof SequenceNode => array_map(
                fn ($item) => $this->extractValue($item),
                $node->getItems()
            ),
            $node instanceof MappingNode => $this->extractMappingValue($node),
            default => null,
        };
    }

    private function extractMappingValue(MappingNode $node): array
    {
        $result = [];

        foreach ($node->getMappingNodeItems() as $item) {
            $key = $this->extractValue($item->getKey());
            $value = $this->extractValue($item->getValue());
            $result[$key] = $value;
        }

        return $result;
    }
}
