<?php

namespace MaxBeckers\YamlParser\Tag;

use MaxBeckers\YamlParser\Api\TagHandlerInterface;

final class TagRegistry
{
    /** @var list<TagHandlerInterface> */
    private array $handlers = [];

    public function register(TagHandlerInterface $handler): void
    {
        $this->handlers[] = $handler;
    }

    public function getHandler(string $tag): ?TagHandlerInterface
    {
        foreach ($this->handlers as $handler) {
            if ($handler->supports($tag)) {
                return $handler;
            }
        }

        return null;
    }
}
