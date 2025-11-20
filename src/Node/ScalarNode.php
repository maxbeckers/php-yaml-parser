<?php

namespace MaxBeckers\YamlParser\Node;

final class ScalarNode extends AbstractNode
{
    public function __construct(
        private readonly mixed $value,
        NodeMetadata $metadata = new NodeMetadata()
    ) {
        parent::__construct($metadata);
    }

    public function getValue(): mixed
    {
        return $this->value;
    }
}
