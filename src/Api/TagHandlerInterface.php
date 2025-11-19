<?php

namespace MaxBeckers\YamlParser\Api;

use MaxBeckers\YamlParser\Node\NodeMetadata;

interface TagHandlerInterface
{
    public function supports(string $tag): bool;
    public function handle(mixed $value, NodeMetadataInterface $metadata = new NodeMetadata()): mixed;
}
