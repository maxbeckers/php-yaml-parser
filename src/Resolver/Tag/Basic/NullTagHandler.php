<?php

namespace MaxBeckers\YamlParser\Resolver\Tag\Basic;

use MaxBeckers\YamlParser\Api\NodeMetadataInterface;
use MaxBeckers\YamlParser\Api\TagHandlerInterface;
use MaxBeckers\YamlParser\Node\NodeMetadata;

final class NullTagHandler implements TagHandlerInterface
{
    public function supports(string $tag): bool
    {
        return $tag === '!!null' || $tag === '!<tag:yaml.org,2002:null>';
    }

    public function handle(mixed $value, NodeMetadataInterface $metadata = new NodeMetadata()): null
    {
        return null;
    }
}
