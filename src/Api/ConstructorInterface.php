<?php

namespace MaxBeckers\YamlParser\Api;

use MaxBeckers\YamlParser\Constructor\ConstructorContext;

interface ConstructorInterface
{
    public static function construct(NodeInterface $node, ConstructorContext $context = new ConstructorContext()): mixed;
}
