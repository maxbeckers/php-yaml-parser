<?php

namespace MaxBeckers\YamlParser\Resolver\Tag\Basic;

use MaxBeckers\YamlParser\Api\NodeMetadataInterface;
use MaxBeckers\YamlParser\Api\TagHandlerInterface;
use MaxBeckers\YamlParser\Node\NodeMetadata;

final class BoolTagHandler implements TagHandlerInterface
{
    public function supports(string $tag): bool
    {
        return $tag === '!!bool' || $tag === '!<tag:yaml.org,2002:bool>';
    }

    public function handle(mixed $value, NodeMetadataInterface $metadata = new NodeMetadata()): bool
    {
        return (bool) $value;
    }
}
