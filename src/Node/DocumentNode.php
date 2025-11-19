<?php

namespace MaxBeckers\YamlParser\Node;

use MaxBeckers\YamlParser\Api\NodeInterface;

final class DocumentNode extends AbstractNode
{
    public const TYPE = 'document';

    public function __construct(
        private readonly NodeInterface $root,
        NodeMetadata $metadata = new NodeMetadata()
    ) {
        parent::__construct($metadata);
    }

    public function getType(): string
    {
        return self::TYPE;
    }

    public function getRoot(): NodeInterface
    {
        return $this->root;
    }
}
