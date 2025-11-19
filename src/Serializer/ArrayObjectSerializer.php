<?php

namespace MaxBeckers\YamlParser\Serializer;

use MaxBeckers\YamlParser\Api\NodeInterface;
use MaxBeckers\YamlParser\Api\SerializerInterface;
use MaxBeckers\YamlParser\Node\DocumentNode;
use MaxBeckers\YamlParser\Node\MappingNode;
use MaxBeckers\YamlParser\Node\ScalarNode;
use MaxBeckers\YamlParser\Node\SequenceNode;
use MaxBeckers\YamlParser\Node\YamlNode;

final class ArrayObjectSerializer implements SerializerInterface
{
    public static function serialize(array|NodeInterface $node, SerializerContext $context = new SerializerContext()): mixed
    {
        return self::serializeInternal($node, $context);
    }

    private static function serializeInternal(array|NodeInterface $node, SerializerContext $context): mixed
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
            $node instanceof SequenceNode => self::serializeSequence($node, $nodeId, $context),
            $node instanceof MappingNode => self::serializeMapping($node, $nodeId, $context),
            $node instanceof DocumentNode => self::serializeInternal($node->getRoot(), $context),
            $node instanceof YamlNode => self::serializeYaml($node, $nodeId, $context),
            default => null,
        };
    }

    private static function serializeYaml(YamlNode $node, string $nodeId, SerializerContext $context): \ArrayObject
    {
        $result = new \ArrayObject();
        $context->addReference($nodeId, $result);

        foreach ($node->getDocuments() as $item) {
            $result[] = self::serializeInternal($item, $context);
        }

        return $result;
    }

    private static function serializeSequence(SequenceNode $node, string $nodeId, SerializerContext $context): \ArrayObject
    {
        $result = new \ArrayObject();
        $context->addReference($nodeId, $result);

        foreach ($node->getItems() as $item) {
            $result[] = self::serializeInternal($item, $context);
        }

        return $result;
    }

    private static function serializeMapping(MappingNode $node, string $nodeId, SerializerContext $context): \ArrayObject
    {
        $result = new \ArrayObject();
        $context->addReference($nodeId, $result);

        foreach ($node->getMappingNodeItems() as $value) {
            $result[$value->getKeySerialized()] = self::serializeInternal($value->getValue(), $context);
        }

        return $result;
    }
}
