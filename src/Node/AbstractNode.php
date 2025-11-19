<?php

namespace MaxBeckers\YamlParser\Node;

use MaxBeckers\YamlParser\Api\NodeInterface;

abstract class AbstractNode implements NodeInterface
{
    public function __construct(
        protected NodeMetadata $metadata = new NodeMetadata()
    ) {
    }

    public function getMetadata(): NodeMetadata
    {
        return $this->metadata;
    }
}
