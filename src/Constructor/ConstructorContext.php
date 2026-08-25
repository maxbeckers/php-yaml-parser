<?php

namespace MaxBeckers\YamlParser\Constructor;

class ConstructorContext
{
    public function __construct(
        public array $references = [],
        public array $inProgress = [],
        public array $inProgressStack = [],
        public array $inProgressPositions = []
    ) {
    }

    public function addReference(int $id, mixed &$value): void
    {
        $this->references[$id] = &$value;
    }

    public function hasReference(int $id): bool
    {
        return isset($this->references[$id]);
    }

    public function &getReference(int $id): mixed
    {
        return $this->references[$id];
    }

    public function removeReference(int $id): void
    {
        unset($this->references[$id]);
    }

    public function markInProgress(int $id): void
    {
        $this->inProgress[$id] = true;
        $this->inProgressPositions[$id] = count($this->inProgressStack);
        $this->inProgressStack[] = $id;
    }

    public function unmarkInProgress(int $id): void
    {
        unset($this->inProgress[$id]);
        unset($this->inProgressPositions[$id]);
        array_pop($this->inProgressStack);
    }

    public function isInProgress(int $id): bool
    {
        return isset($this->inProgress[$id]);
    }

    /**
     * @return array<int>
     */
    public function getCycleNodeIds(int $id): array
    {
        $startIndex = $this->inProgressPositions[$id] ?? 0;

        return array_slice($this->inProgressStack, $startIndex);
    }
}
