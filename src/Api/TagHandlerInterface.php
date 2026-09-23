<?php

namespace MaxBeckers\YamlParser\Api;

interface TagHandlerInterface
{
    public function supports(string $tag): bool;

    public function handle(mixed $value, NodeMetadataInterface $metadata): mixed;
}
