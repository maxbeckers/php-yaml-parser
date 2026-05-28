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
    public static function construct(array|NodeInterface $node, ConstructorContext $context = new ConstructorContext()): mixed
    {
        return self::constructInternal($node, $context);
    }

    private static function constructInternal(array|NodeInterface $node, ConstructorContext $context): mixed
    {
        if (!$node instanceof NodeInterface) {
            return $node;
        }

        $nodeId = spl_object_id($node);

        if ($context->hasReference($nodeId)) {
            return $context->getReference($nodeId);
        }

        return match (true) {
            $node instanceof ScalarNode => $node->getValue(),
            $node instanceof SequenceNode => self::constructSequence($node, $nodeId, $context),
            $node instanceof MappingNode => self::constructMapping($node, $nodeId, $context),
            $node instanceof DocumentNode => self::constructInternal($node->getRoot(), $context),
            $node instanceof YamlNode => self::constructYaml($node, $nodeId, $context),
            default => null,
        };
    }

    private static function constructYaml(YamlNode $node, string $nodeId, ConstructorContext $context): \ArrayObject
    {
        $result = new \ArrayObject();
        $context->addReference($nodeId, $result);

        foreach ($node->getDocuments() as $item) {
            $result[] = self::constructInternal($item, $context);
        }

        return $result;
    }

    private static function constructSequence(SequenceNode $node, string $nodeId, ConstructorContext $context): \ArrayObject
    {
        $result = new \ArrayObject();
        $context->addReference($nodeId, $result);

        foreach ($node->getItems() as $item) {
            $result[] = self::constructInternal($item, $context);
        }

        return $result;
    }

    private static function constructMapping(MappingNode $node, string $nodeId, ConstructorContext $context): \ArrayObject
    {
        $result = new \ArrayObject();
        $context->addReference($nodeId, $result);

        foreach ($node->getMappingNodeItems() as $value) {
            $result[$value->getKeySerialized()] = self::constructInternal($value->getValue(), $context);
        }

        return $result;
    }
}
