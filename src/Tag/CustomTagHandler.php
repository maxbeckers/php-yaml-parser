<?php

namespace MaxBeckers\YamlParser\Tag;

use MaxBeckers\YamlParser\Api\NodeMetadataInterface;
use MaxBeckers\YamlParser\Api\TagHandlerInterface;

final readonly class CustomTagHandler implements TagHandlerInterface
{
    public function __construct(
        private string $tag,
        private \Closure $handler,
    ) {
    }

    public function supports(string $tag): bool
    {
        return $this->tag === $tag;
    }

    public function handle(mixed $value, NodeMetadataInterface $metadata): mixed
    {
        return ($this->handler)($value, $metadata);
    }
}
