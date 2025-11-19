<?php

namespace MaxBeckers\YamlParser\Api;

use MaxBeckers\YamlParser\Node\NodeMetadata;

interface NodeInterface
{
    public function getType(): string;
    public function getMetadata(): NodeMetadata;
}
