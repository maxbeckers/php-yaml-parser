<?php

namespace MaxBeckers\YamlParser\Resolver\Anchor;

use MaxBeckers\YamlParser\Api\NodeInterface;
use MaxBeckers\YamlParser\Exception\ResolverException;
use MaxBeckers\YamlParser\Node\DocumentNode;
use MaxBeckers\YamlParser\Node\MappingNode;
use MaxBeckers\YamlParser\Node\MappingNodeItem;
use MaxBeckers\YamlParser\Node\SequenceNode;
use MaxBeckers\YamlParser\Node\YamlNode;

final class AnchorResolver
{
    public static function resolve(NodeInterface $ast): NodeInterface
    {
        $context = new AnchorResolverContext();
        self::collectAnchors($context, $ast);
        $context->resetForAliasHandling();

        return self::resolveNode($context, $ast);
    }

    private static function collectAnchors(AnchorResolverContext $context, NodeInterface $node): void
    {
        if ($anchor = $node->getMetadata()->getAnchor()) {
            $context->addAnchor($anchor, $node);
        }

        match (true) {
            $node instanceof SequenceNode => self::collectAnchorsFromSequence($context, $node),
            $node instanceof MappingNode => self::collectAnchorsFromMapping($context, $node),
            $node instanceof DocumentNode => self::collectAnchorsFromDocument($context, $node),
            $node instanceof YamlNode => self::collectAnchorsFromYaml($context, $node),
            default => null,
        };
    }

    private static function collectAnchorsFromYaml(AnchorResolverContext $context, YamlNode $node): void
    {
        foreach ($node->getDocuments() as $item) {
            self::collectAnchors($context, $item);
        }
    }

    private static function collectAnchorsFromDocument(AnchorResolverContext $context, DocumentNode $node): void
    {
        $context->nextDocument();
        self::collectAnchors($context, $node->getRoot());
    }

    private static function collectAnchorsFromSequence(AnchorResolverContext $context, SequenceNode $node): void
    {
        foreach ($node->getItems() as $item) {
            self::collectAnchors($context, $item);
        }
    }

    private static function collectAnchorsFromMapping(AnchorResolverContext $context, MappingNode $node): void
    {
        foreach ($node->getMappingNodeItems() as $item) {
            self::collectAnchors($context, $item->getKey());
            self::collectAnchors($context, $item->getValue());
        }
    }

    private static function resolveNode(AnchorResolverContext $context, NodeInterface $node): NodeInterface
    {
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

            return self::resolveNode($context, $anchoredNode);
        }

        if ($anchor = $node->getMetadata()->getAnchor()) {
            $context->incrementAnchorOccurrence($anchor);
            if ($context->isResolved($anchor)) {
                return $context->getResolved($anchor);
            }

            if ($context->isResolving($anchor)) {
                return $context->getResolvingNode($anchor);
            }

            $resolved = self::createEmptyNode($node);
            $context->startResolving($anchor, $resolved);
            self::populateNode($context, $node, $resolved);
            $context->stopResolving($anchor);
            $context->addResolved($anchor, $resolved);

            return $resolved;
        }

        return self::resolveNodeContent($context, $node);
    }

    private static function createEmptyNode(NodeInterface $node): NodeInterface
    {
        return match (true) {
            $node instanceof SequenceNode => new SequenceNode([], $node->getMetadata()),
            $node instanceof MappingNode => new MappingNode([], $node->getMetadata()),
            default => $node,
        };
    }

    private static function populateNode(AnchorResolverContext $context, NodeInterface $node, NodeInterface $resolved): void
    {
        if ($node instanceof MappingNode && $resolved instanceof MappingNode) {
            foreach ($node->getMappingNodeItems() as $value) {
                $resolved->addMappingItem(new MappingNodeItem(
                    self::resolveNode($context, $value->getKey()),
                    self::resolveNode($context, $value->getValue())
                ));
            }
        } elseif ($node instanceof SequenceNode && $resolved instanceof SequenceNode) {
            foreach ($node->getItems() as $item) {
                $resolved->addItem(self::resolveNode($context, $item));
            }
        }
    }

    private static function resolveNodeContent(AnchorResolverContext $context, NodeInterface $node): NodeInterface
    {
        return match (true) {
            $node instanceof SequenceNode => self::resolveSequence($context, $node),
            $node instanceof MappingNode => self::resolveMapping($context, $node),
            $node instanceof DocumentNode => self::resolveDocument($context, $node),
            $node instanceof YamlNode => self::resolveYaml($context, $node),
            default => $node,
        };
    }

    private static function resolveYaml(AnchorResolverContext $context, YamlNode $node): SequenceNode
    {
        $resolved = new SequenceNode([], $node->getMetadata());

        if ($anchor = $node->getMetadata()->getAnchor()) {
            $context->addResolved($anchor, $resolved);
        }

        foreach ($node->getDocuments() as $item) {
            $resolved->addItem(self::resolveNode($context, $item));
        }

        return $resolved;
    }

    private static function resolveDocument(AnchorResolverContext $context, DocumentNode $node): DocumentNode
    {
        $context->nextDocument();

        return new DocumentNode(self::resolveNode($context, $node->getRoot()), $node->getMetadata());
    }

    private static function resolveSequence(AnchorResolverContext $context, SequenceNode $node): NodeInterface
    {
        $resolved = new SequenceNode([], $node->getMetadata());

        if ($anchor = $node->getMetadata()->getAnchor()) {
            $context->addResolved($anchor, $resolved);
        }

        foreach ($node->getItems() as $item) {
            $resolved->addItem(self::resolveNode($context, $item));
        }

        return $resolved;
    }


    private static function resolveMapping(AnchorResolverContext $context, MappingNode $node): NodeInterface
    {
        $resolved = new MappingNode([], $node->getMetadata());

        if ($anchor = $node->getMetadata()->getAnchor()) {
            $context->addResolved($anchor, $resolved);
        }

        foreach ($node->getMappingNodeItems() as $value) {
            $resolved->addMappingItem(new MappingNodeItem(
                self::resolveNode($context, $value->getKey()),
                self::resolveNode($context, $value->getValue())
            ));
        }

        return $resolved;
    }
}
