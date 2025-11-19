<?php

namespace MaxBeckers\YamlParser\Resolver\Anchor;

final class AnchorOccurrence
{
    public function __construct(
        private int $occurrence = 0,
        private bool $implicit = false
    ) {
    }

    public function getOccurrence(): int
    {
        return $this->occurrence;
    }

    public function incrementOccurrence(): void
    {
        if ($this->implicit) {
            $this->implicit = false;

            return;
        }
        $this->occurrence++;
    }
}
