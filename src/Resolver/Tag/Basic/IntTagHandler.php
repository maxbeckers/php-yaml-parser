<?php

namespace MaxBeckers\YamlParser\Resolver\Tag\Basic;

use MaxBeckers\YamlParser\Api\NodeMetadataInterface;
use MaxBeckers\YamlParser\Api\TagHandlerInterface;
use MaxBeckers\YamlParser\Node\NodeMetadata;

final class IntTagHandler implements TagHandlerInterface
{
    public function supports(string $tag): bool
    {
        return $tag === '!!int' || $tag === '!<tag:yaml.org,2002:int>';
    }

    public function handle(mixed $value, NodeMetadataInterface $metadata = new NodeMetadata()): int
    {
        return (int) $value;
    }
}
