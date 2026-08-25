<?php

namespace MaxBeckers\YamlParser\Constructor;

use MaxBeckers\YamlParser\Api\ConstructorInterface;
use MaxBeckers\YamlParser\Api\NodeInterface;
use MaxBeckers\YamlParser\Node\DocumentNode;
use MaxBeckers\YamlParser\Node\MappingNode;
use MaxBeckers\YamlParser\Node\ScalarNode;
use MaxBeckers\YamlParser\Node\SequenceNode;
use MaxBeckers\YamlParser\Node\YamlNode;

final class ArrayObjectConstructor implements ConstructorInterface
{
    public static function construct(
        array|NodeInterface $node,
        ConstructorContext $context = new ConstructorContext(),
        bool $preferPlainArrays = false
    ): mixed {
        return self::constructInternal($node, $context, $preferPlainArrays);
    }

    private static function constructInternal(
        array|NodeInterface $node,
        ConstructorContext $context,
        bool $preferPlainArrays
    ): mixed {
        if (!$node instanceof NodeInterface) {
            return $node;
        }

        if ($node instanceof ScalarNode) {
            return $node->getValue();
        }

        if ($node instanceof DocumentNode) {
            return self::constructInternal($node->getRoot(), $context, $preferPlainArrays);
        }

        $nodeId = spl_object_id($node);

        if ($context->hasReference($nodeId)) {
            if ($preferPlainArrays && $context->isInProgress($nodeId)) {
                foreach ($context->getCycleNodeIds($nodeId) as $cycleNodeId) {
                    self::promoteInProgressReference($cycleNodeId, $context);
                }
            }

            return $context->getReference($nodeId);
        }

        return match (true) {
            $node instanceof SequenceNode => self::constructSequence($node, $nodeId, $context, $preferPlainArrays),
            $node instanceof MappingNode => self::constructMapping($node, $nodeId, $context, $preferPlainArrays),
            $node instanceof YamlNode => self::constructYaml($node, $nodeId, $context, $preferPlainArrays),
            default => null,
        };
    }

    private static function constructYaml(
        YamlNode $node,
        int $nodeId,
        ConstructorContext $context,
        bool $preferPlainArrays
    ): \ArrayObject|array {
        if ($preferPlainArrays) {
            $result = [];
            $context->addReference($nodeId, $result);
            $context->markInProgress($nodeId);
            $reference = &$context->getReference($nodeId);

            foreach ($node->getDocuments() as $item) {
                $value = self::constructInternal($item, $context, $preferPlainArrays);
                $reference[] = $value;
            }

            $context->unmarkInProgress($nodeId);

            if ($reference instanceof \ArrayObject) {
                return $reference;
            }

            $context->removeReference($nodeId);

            return $reference;
        }

        $result = new \ArrayObject();
        $context->addReference($nodeId, $result);

        foreach ($node->getDocuments() as $item) {
            $result[] = self::constructInternal($item, $context, $preferPlainArrays);
        }

        return $result;
    }

    private static function constructSequence(
        SequenceNode $node,
        int $nodeId,
        ConstructorContext $context,
        bool $preferPlainArrays
    ): \ArrayObject|array {
        if ($preferPlainArrays) {
            $result = [];
            $context->addReference($nodeId, $result);
            $context->markInProgress($nodeId);
            $reference = &$context->getReference($nodeId);

            foreach ($node->getItems() as $item) {
                $value = self::constructInternal($item, $context, $preferPlainArrays);
                $reference[] = $value;
            }

            $context->unmarkInProgress($nodeId);

            if ($reference instanceof \ArrayObject) {
                return $reference;
            }

            $context->removeReference($nodeId);

            return $reference;
        }

        $result = new \ArrayObject();
        $context->addReference($nodeId, $result);

        foreach ($node->getItems() as $item) {
            $result[] = self::constructInternal($item, $context, $preferPlainArrays);
        }

        return $result;
    }

    private static function constructMapping(
        MappingNode $node,
        int $nodeId,
        ConstructorContext $context,
        bool $preferPlainArrays
    ): \ArrayObject|array {
        if ($preferPlainArrays) {
            $result = [];
            $context->addReference($nodeId, $result);
            $context->markInProgress($nodeId);
            $reference = &$context->getReference($nodeId);

            foreach ($node->getMappingNodeItems() as $value) {
                $key = $value->getKeySerialized();
                $constructed = self::constructInternal($value->getValue(), $context, $preferPlainArrays);
                $reference[$key] = $constructed;
            }

            $context->unmarkInProgress($nodeId);

            if ($reference instanceof \ArrayObject) {
                return $reference;
            }

            $context->removeReference($nodeId);

            return $reference;
        }

        $result = new \ArrayObject();
        $context->addReference($nodeId, $result);

        foreach ($node->getMappingNodeItems() as $value) {
            $result[$value->getKeySerialized()] = self::constructInternal($value->getValue(), $context, $preferPlainArrays);
        }

        return $result;
    }

    private static function promoteInProgressReference(int $nodeId, ConstructorContext $context): void
    {
        $reference = &$context->getReference($nodeId);
        if ($reference instanceof \ArrayObject) {
            return;
        }

        $reference = new \ArrayObject(is_array($reference) ? $reference : []);
    }
}
