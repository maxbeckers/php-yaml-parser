<?php

namespace MaxBeckers\YamlParser\Node;

final class ScalarNode extends AbstractNode
{
    public const TYPE = 'scalar';

    public function __construct(
        private readonly mixed $value,
        NodeMetadata $metadata = new NodeMetadata()
    ) {
        parent::__construct($metadata);
    }

    public function getType(): string
    {
        return self::TYPE;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }
}
