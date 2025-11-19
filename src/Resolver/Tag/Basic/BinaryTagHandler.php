<?php

namespace MaxBeckers\YamlParser\Resolver\Tag\Basic;

use MaxBeckers\YamlParser\Api\NodeMetadataInterface;
use MaxBeckers\YamlParser\Api\TagHandlerInterface;
use MaxBeckers\YamlParser\Node\NodeMetadata;

final class BinaryTagHandler implements TagHandlerInterface
{
    public function supports(string $tag): bool
    {
        return $tag === '!!binary' || $tag === '!<tag:yaml.org,2002:binary>';
    }

    public function handle(mixed $value, NodeMetadataInterface $metadata = new NodeMetadata()): string|false
    {
        return base64_decode($value, true);
    }
}
