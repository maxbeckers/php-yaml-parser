<?php

namespace MaxBeckers\YamlParser\Resolver\Anchor;

use MaxBeckers\YamlParser\Api\NodeInterface;

final class AnchorResolverContext
{
    public function __construct(
        private array $anchors = [],
        private array $resolved = [],
        private array $resolving = [],
        private array $resolving_nodes = [],
        private int $currentDocument = 0,
        /** @var AnchorOccurrence */
        private array $currentAnchorOccurrence = []
    ) {
    }

    public function addAnchor(string $name, NodeInterface &$value): void
    {
        if (!isset($this->currentAnchorOccurrence[$name])) {
            $this->currentAnchorOccurrence[$name] = new AnchorOccurrence();
        }

        $this->currentAnchorOccurrence[$name]->incrementOccurrence();
        $this->anchors[$this->createCacheKey($name)] = &$value;
    }

    public function incrementAnchorOccurrence(string $name, bool $implicit = false): void
    {
        if ($implicit === true) {
            $this->currentAnchorOccurrence[$name] = new AnchorOccurrence(1, true);

            return;
        }

        if (!isset($this->currentAnchorOccurrence[$name])) {
            $this->currentAnchorOccurrence[$name] = new AnchorOccurrence();
        }

        $this->currentAnchorOccurrence[$name]->incrementOccurrence();
    }

    public function &getAnchor(string $name): ?NodeInterface
    {
        return $this->anchors[$this->createCacheKey($name)];
    }

    public function hasAnchor(string $name): bool
    {
        return isset($this->anchors[$this->createCacheKey($name)]);
    }

    public function addResolved(string $name, NodeInterface &$node): void
    {
        $this->resolved[$this->createCacheKey($name)] = &$node;
    }

    public function isResolved(string $name): bool
    {
        return isset($this->resolved[$this->createCacheKey($name)]);
    }

    public function &getResolved(string $name): NodeInterface
    {
        return $this->resolved[$this->createCacheKey($name)];
    }

    public function startResolving(string $name, NodeInterface &$node): void
    {
        $this->resolving[$this->createCacheKey($name)] = true;
        $this->resolving_nodes[$this->createCacheKey($name)] = &$node;
    }

    public function stopResolving(string $name): void
    {
        unset($this->resolving[$this->createCacheKey($name)]);
        unset($this->resolving_nodes[$this->createCacheKey($name)]);
    }

    public function isResolving(string $name): bool
    {
        return isset($this->resolving[$this->createCacheKey($name)]);
    }

    public function &getResolvingNode(string $name): ?NodeInterface
    {
        return $this->resolving_nodes[$this->createCacheKey($name)];
    }

    public function nextDocument(): void
    {
        $this->currentDocument++;
    }

    public function resetForAliasHandling(): void
    {
        $this->currentDocument = 0;
        foreach ($this->currentAnchorOccurrence as $name => $node) {
            $this->currentAnchorOccurrence[$name] = new AnchorOccurrence();
        }
    }

    private function createCacheKey(string $name): string
    {
        return $this->currentDocument . '::' . $name . '::' . ($this->currentAnchorOccurrence[$name]->getOccurrence() ?? 0);
    }
}
