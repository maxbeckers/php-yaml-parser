<?php

namespace MaxBeckers\YamlParser\Api;

use MaxBeckers\YamlParser\Serializer\SerializerContext;

interface SerializerInterface
{
    public static function serialize(NodeInterface $node, SerializerContext $context = new SerializerContext()): mixed;
}
