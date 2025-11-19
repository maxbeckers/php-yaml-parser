<?php

namespace MaxBeckers\YamlParser\Api;

use MaxBeckers\YamlParser\Node\NodeMetadata;
use MaxBeckers\YamlParser\Parser\ParserContext;

interface TokenParserInterface
{
    public static function supports(ParserContext $context): bool;
    public static function parse(ParserContext $context, NodeMetadata $metadata = new NodeMetadata(), bool $isKey = false): NodeInterface;
}
