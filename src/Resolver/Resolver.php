<?php

namespace MaxBeckers\YamlParser\Resolver;

use MaxBeckers\YamlParser\Api\NodeInterface;
use MaxBeckers\YamlParser\Exception\ResolverException;
use MaxBeckers\YamlParser\Node\DocumentNode;
use MaxBeckers\YamlParser\Node\MappingNode;
use MaxBeckers\YamlParser\Node\MappingNodeItem;
use MaxBeckers\YamlParser\Node\ScalarNode;
use MaxBeckers\YamlParser\Node\SequenceNode;
use MaxBeckers\YamlParser\Node\YamlNode;
use MaxBeckers\YamlParser\Resolver\Tag\TagRegistry;

final readonly class Resolver
{
    public function __construct(
        private TagRegistry $tagRegistry
    ) {
    }

    public function resolve(NodeInterface $ast, ?int $maxDepth = null): NodeInterface
    {
        $ast = $this->applyTags($ast);

        $context = new ResolverContext($this->tagRegistry, $maxDepth);

        $this->collectAnchors($context, $ast);

        $context->resetForAliasHandling();

        return $this->resolveNode($context, $ast);
    }

    /**
     * Apply tags to the AST (Phase 1).
     * This must happen before anchor collection to ensure tags modify nodes correctly.
     */
    private function applyTags(NodeInterface $node): NodeInterface
    {
        if ($tag = $node->getMetadata()->getTag()) {
            $handler = $this->tagRegistry->getHandler($tag);
            if ($handler) {
                $value = $this->extractValue($node);
                $processedValue = $handler->handle($value, $node->getMetadata());

                return new ScalarNode($processedValue, $node->getMetadata());
            }
        }

        return match (true) {
            $node instanceof SequenceNode => $this->applyTagsToSequence($node),
            $node instanceof MappingNode => $this->applyTagsToMapping($node),
            $node instanceof DocumentNode => new DocumentNode($this->applyTags($node->getRoot()), $node->getMetadata()),
            default => $node,
        };
    }

    private function applyTagsToSequence(SequenceNode $node): SequenceNode
    {
        $processed = new SequenceNode([], $node->getMetadata());

        foreach ($node->getItems() as $item) {
            $processed->addItem($this->applyTags($item));
        }

        return $processed;
    }

    private function applyTagsToMapping(MappingNode $node): MappingNode
    {
        $processed = new MappingNode([], $node->getMetadata());

        foreach ($node->getMappingNodeItems() as $item) {
            $processed->addMappingItem(new MappingNodeItem(
                $this->applyTags($item->getKey()),
                $this->applyTags($item->getValue())
            ));
        }

        return $processed;
    }

    /**
     * Collect all anchors into indexed maps for O(1) lookup.
     */
    private function collectAnchors(ResolverContext $context, NodeInterface $node): void
    {
        if ($anchor = $node->getMetadata()->getAnchor()) {
            $context->addAnchor($anchor, $node);
        }

        match (true) {
            $node instanceof SequenceNode => $this->collectAnchorsFromSequence($context, $node),
            $node instanceof MappingNode => $this->collectAnchorsFromMapping($context, $node),
            $node instanceof DocumentNode => $this->collectAnchorsFromDocument($context, $node),
            $node instanceof YamlNode => $this->collectAnchorsFromYaml($context, $node),
            default => null,
        };
    }

    private function collectAnchorsFromYaml(ResolverContext $context, YamlNode $node): void
    {
        foreach ($node->getDocuments() as $item) {
            $this->collectAnchors($context, $item);
        }
    }

    private function collectAnchorsFromDocument(ResolverContext $context, DocumentNode $node): void
    {
        $context->nextDocument();
        $this->collectAnchors($context, $node->getRoot());
    }

    private function collectAnchorsFromSequence(ResolverContext $context, SequenceNode $node): void
    {
        foreach ($node->getItems() as $item) {
            $this->collectAnchors($context, $item);
        }
    }

    private function collectAnchorsFromMapping(ResolverContext $context, MappingNode $node): void
    {
        foreach ($node->getMappingNodeItems() as $item) {
            $this->collectAnchors($context, $item->getKey());
            $this->collectAnchors($context, $item->getValue());
        }
    }

    private function resolveNode(ResolverContext $context, NodeInterface $node): NodeInterface
    {
        $context->enterNodeDepth();

        try {
            if ($alias = $node->getMetadata()->getAlias()) {
                if (!$context->hasAnchor($alias)) {
                    $context->incrementAnchorOccurrence($alias, true);
                    if (!$context->hasAnchor($alias)) {
                        throw new ResolverException("Unknown alias: *{$alias}");
                    }
                }

                if ($context->isResolved($alias)) {
                    return $context->getResolved($alias);
                }

                if ($context->isResolving($alias)) {
                    return $context->getResolvingNode($alias);
                }

                $anchoredNode = $context->getAnchor($alias);

                return $this->resolveNode($context, $anchoredNode);
            }

            if ($anchor = $node->getMetadata()->getAnchor()) {
                $context->incrementAnchorOccurrence($anchor);
                if ($context->isResolved($anchor)) {
                    return $context->getResolved($anchor);
                }

                if ($context->isResolving($anchor)) {
                    return $context->getResolvingNode($anchor);
                }

                $resolved = $this->createEmptyNode($node);
                $context->startResolving($anchor, $resolved);
                $this->populateNode($context, $node, $resolved);
                $context->stopResolving($anchor);
                $context->addResolved($anchor, $resolved);

                return $resolved;
            }

            return $this->resolveNodeContent($context, $node);
        } finally {
            $context->exitNodeDepth();
        }
    }

    private function resolveNodeContent(ResolverContext $context, NodeInterface $node): NodeInterface
    {
        return match (true) {
            $node instanceof SequenceNode => $this->resolveSequence($context, $node),
            $node instanceof MappingNode => $this->resolveMappingWithMerge($context, $node),
            $node instanceof DocumentNode => $this->resolveDocument($context, $node),
            $node instanceof YamlNode => $this->resolveYaml($context, $node),
            default => $node,
        };
    }

    private function resolveMappingWithMerge(ResolverContext $context, MappingNode $node): MappingNode
    {
        $mergedItems = [];
        $regularItems = [];

        foreach ($node->getMappingNodeItems() as $item) {
            if ($item->getKey()->getMetadata()->isMergeKey()) {
                $mergedItems = array_merge($mergedItems, $this->extractMergeItems($context, $item->getValue()));
            } else {
                $regularItems[] = $item;
            }
        }

        $resolved = new MappingNode([], $node->getMetadata());

        foreach ($mergedItems as $item) {
            $resolved->addMappingItem(new MappingNodeItem(
                $this->resolveNode($context, $item->getKey()),
                $this->resolveNode($context, $item->getValue())
            ));
        }

        foreach ($regularItems as $item) {
            $resolved->addMappingItem(new MappingNodeItem(
                $this->resolveNode($context, $item->getKey()),
                $this->resolveNode($context, $item->getValue())
            ));
        }

        return $resolved;
    }

    private function extractMergeItems(ResolverContext $context, NodeInterface $node): array
    {
        $resolvedNode = $this->resolveNode($context, $node);

        if ($resolvedNode instanceof MappingNode) {
            return $resolvedNode->getMappingNodeItems();
        }

        if ($resolvedNode instanceof SequenceNode) {
            $items = [];
            foreach ($resolvedNode->getItems() as $item) {
                $resolvedItem = $this->resolveNode($context, $item);
                if ($resolvedItem instanceof MappingNode) {
                    $items = array_merge($items, $resolvedItem->getMappingNodeItems());
                }
            }

            return $items;
        }

        throw new ResolverException('Merge key value must be a mapping or sequence of mappings');
    }

    private function createEmptyNode(NodeInterface $node): NodeInterface
    {
        return match (true) {
            $node instanceof SequenceNode => new SequenceNode([], $node->getMetadata()),
            $node instanceof MappingNode => new MappingNode([], $node->getMetadata()),
            default => $node,
        };
    }

    private function populateNode(ResolverContext $context, NodeInterface $node, NodeInterface $resolved): void
    {
        if ($node instanceof MappingNode && $resolved instanceof MappingNode) {
            foreach ($node->getMappingNodeItems() as $item) {
                $resolved->addMappingItem(new MappingNodeItem(
                    $this->resolveNode($context, $item->getKey()),
                    $this->resolveNode($context, $item->getValue())
                ));
            }
        } elseif ($node instanceof SequenceNode && $resolved instanceof SequenceNode) {
            foreach ($node->getItems() as $item) {
                $resolved->addItem($this->resolveNode($context, $item));
            }
        }
    }

    private function resolveYaml(ResolverContext $context, YamlNode $node): SequenceNode
    {
        $resolved = new SequenceNode([], $node->getMetadata());

        if ($anchor = $node->getMetadata()->getAnchor()) {
            $context->addResolved($anchor, $resolved);
        }

        foreach ($node->getDocuments() as $item) {
            $resolved->addItem($this->resolveNode($context, $item));
        }

        return $resolved;
    }

    private function resolveDocument(ResolverContext $context, DocumentNode $node): DocumentNode
    {
        $context->nextDocument();

        return new DocumentNode($this->resolveNode($context, $node->getRoot()), $node->getMetadata());
    }

    private function resolveSequence(ResolverContext $context, SequenceNode $node): SequenceNode
    {
        $resolved = new SequenceNode([], $node->getMetadata());

        if ($anchor = $node->getMetadata()->getAnchor()) {
            $context->addResolved($anchor, $resolved);
        }

        foreach ($node->getItems() as $item) {
            $resolved->addItem($this->resolveNode($context, $item));
        }

        return $resolved;
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
