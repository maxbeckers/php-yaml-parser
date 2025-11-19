<?php

namespace MaxBeckers\YamlParser\Resolver\Tag\Basic;

use MaxBeckers\YamlParser\Api\NodeMetadataInterface;
use MaxBeckers\YamlParser\Api\TagHandlerInterface;
use MaxBeckers\YamlParser\Node\NodeMetadata;

final class FloatTagHandler implements TagHandlerInterface
{
    public function supports(string $tag): bool
    {
        return $tag === '!!float' || $tag === '!<tag:yaml.org,2002:float>';
    }

    public function handle(mixed $value, NodeMetadataInterface $metadata = new NodeMetadata()): int
    {
        return (float) $value;
    }
}
