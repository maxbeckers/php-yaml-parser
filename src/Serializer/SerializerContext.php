<?php

namespace MaxBeckers\YamlParser\Serializer;

class SerializerContext
{
    public function __construct(
        public array $references = [],
        public array $converted = []
    ) {
    }

    public function addReference(string $id, object &$value): void
    {
        $this->references[$id] = &$value;
    }

    public function hasReference(string $id): bool
    {
        return isset($this->references[$id]);
    }

    public function &getReference(string $id): object
    {
        return $this->references[$id];
    }

    public function addConverted(string $id, object &$value): void
    {
        $this->converted[$id] = &$value;
    }

    public function hasConverted(string $id): bool
    {
        return isset($this->converted[$id]);
    }

    public function &getConverted(string $id): object
    {
        return $this->converted[$id];
    }
}
