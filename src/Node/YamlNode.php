<?php

namespace MaxBeckers\YamlParser\Node;

use MaxBeckers\YamlParser\Api\NodeInterface;

final class YamlNode extends AbstractNode
{
    public const TYPE = 'yaml';

    public function __construct(
        /** @var NodeInterface[] $documents */
        private array $documents = [],
        NodeMetadata $metadata = new NodeMetadata()
    ) {
        parent::__construct($metadata);
    }

    public function getType(): string
    {
        return self::TYPE;
    }

    public function getDocuments(): array
    {
        return $this->documents;
    }

    public function addDocument(DocumentNode $document): void
    {
        $this->documents[] = $document;
    }
}
