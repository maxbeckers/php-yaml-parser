<?php

namespace MaxBeckers\YamlParser\Resolver\Merge;

use MaxBeckers\YamlParser\Api\NodeInterface;
use MaxBeckers\YamlParser\Exception\ResolverException;
use MaxBeckers\YamlParser\Node\DocumentNode;
use MaxBeckers\YamlParser\Node\MappingNode;
use MaxBeckers\YamlParser\Node\MappingNodeItem;
use MaxBeckers\YamlParser\Node\SequenceNode;
use MaxBeckers\YamlParser\Node\YamlNode;

final class MergeResolver
{
    public static function resolve(NodeInterface $ast): NodeInterface
    {
        return self::resolveNode($ast);
    }

    private static function resolveNode(NodeInterface $node): NodeInterface
    {
        if ($node->getMetadata()->getAnchor()) {
            return $node;
        }

        return match (true) {
            $node instanceof YamlNode => self::resolveYaml($node),
            $node instanceof DocumentNode => self::resolveDocument($node),
            $node instanceof SequenceNode => self::resolveSequence($node),
            $node instanceof MappingNode => self::resolveMapping($node),
            default => $node,
        };
    }

    private static function resolveYaml(YamlNode $node): YamlNode
    {
        $documents = [];
        foreach ($node->getDocuments() as $document) {
            $documents[] = self::resolveNode($document);
        }

        return new YamlNode($documents, $node->getMetadata());
    }

    private static function resolveDocument(DocumentNode $node): DocumentNode
    {
        return new DocumentNode(
            self::resolveNode($node->getRoot()),
            $node->getMetadata()
        );
    }

    private static function resolveSequence(SequenceNode $node): SequenceNode
    {
        $items = [];
        foreach ($node->getItems() as $item) {
            $items[] = self::resolveNode($item);
        }

        return new SequenceNode($items, $node->getMetadata());
    }

    private static function resolveMapping(MappingNode $node): MappingNode
    {
        $mergedItems = [];
        $regularItems = [];

        foreach ($node->getMappingNodeItems() as $key => $value) {
            if ($value->getKey()->getMetadata()->isMergeKey()) {
                $mergedItems = array_merge($mergedItems, self::extractMergeItems($value->getValue()));
            } else {
                $regularItems[$key] = $value;
            }
        }

        $resolved = new MappingNode([], $node->getMetadata());

        foreach ($mergedItems as $item) {
            $resolved->addMappingItem(new MappingNodeItem(
                self::resolveNode($item->getKey()),
                self::resolveNode($item->getValue())
            ));
        }

        foreach ($regularItems as $value) {
            $resolved->addMappingItem(new MappingNodeItem(
                self::resolveNode($value->getKey()),
                self::resolveNode($value->getValue())
            ));
        }

        return $resolved;
    }

    /**
     * @return MappingNodeItem[]
     */
    private static function extractMergeItems(NodeInterface $node): array
    {
        $resolvedNode = self::resolveNode($node);

        if ($resolvedNode instanceof MappingNode) {
            return $resolvedNode->getMappingNodeItems();
        }

        if ($resolvedNode instanceof SequenceNode) {
            $items = [];
            foreach ($resolvedNode->getItems() as $item) {
                $resolvedItem = self::resolveNode($item);
                if ($resolvedItem instanceof MappingNode) {
                    $items = array_merge($items, $resolvedItem->getMappingNodeItems());
                }
            }

            return $items;
        }

        throw new ResolverException('Merge key value must be a mapping or sequence of mappings');
    }
}
