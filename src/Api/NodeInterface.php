<?php

namespace MaxBeckers\YamlParser\Api;

use MaxBeckers\YamlParser\Node\NodeMetadata;

interface NodeInterface
{
    public function getMetadata(): NodeMetadata;
}
