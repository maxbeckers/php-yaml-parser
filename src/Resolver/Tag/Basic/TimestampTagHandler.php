<?php

namespace MaxBeckers\YamlParser\Resolver\Tag\Basic;

use MaxBeckers\YamlParser\Api\NodeMetadataInterface;
use MaxBeckers\YamlParser\Api\TagHandlerInterface;
use MaxBeckers\YamlParser\Exception\TagHandlerException;
use MaxBeckers\YamlParser\Node\NodeMetadata;

final class TimestampTagHandler implements TagHandlerInterface
{
    public function supports(string $tag): bool
    {
        return $tag === '!!timestamp';
    }

    public function handle(mixed $value, NodeMetadataInterface $metadata = new NodeMetadata()): ?\DateTimeImmutable
    {
        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception $e) {
            throw new TagHandlerException('Invalid timestamp value: ' . $value, 0, $e);
        }
    }
}
