<?php

namespace MaxBeckers\YamlParser\Tests;

use MaxBeckers\YamlParser\Config\ParsingConfig;
use MaxBeckers\YamlParser\Node\DocumentNode;
use MaxBeckers\YamlParser\Node\MappingNode;
use MaxBeckers\YamlParser\Node\ScalarNode;
use MaxBeckers\YamlParser\Node\SequenceNode;
use MaxBeckers\YamlParser\YamlParser;
use PHPUnit\Framework\TestCase;

final class MetadataCollectionTest extends TestCase
{
    public function testParserCollectsAccurateLineAndColumnMetadata(): void
    {
        $yaml = <<<'YAML'
name: Jane
items:
  - one
YAML;

        $parser = new YamlParser(config: new ParsingConfig(preserveMetadata: true));
        $parser->parse($yaml);

        $rootMapping = $this->getFirstDocumentRootMapping($parser);

        $nameEntry = $this->findMappingItemByScalarKey($rootMapping, 'name');
        $this->assertSame(1, $nameEntry->getKey()->getMetadata()->getLine());
        $this->assertSame(0, $nameEntry->getKey()->getMetadata()->getColumn());
        $this->assertSame(1, $nameEntry->getValue()->getMetadata()->getLine());
        $this->assertSame(10, $nameEntry->getValue()->getMetadata()->getColumn());

        $itemsEntry = $this->findMappingItemByScalarKey($rootMapping, 'items');
        $this->assertSame(2, $itemsEntry->getKey()->getMetadata()->getLine());
        $this->assertSame(0, $itemsEntry->getKey()->getMetadata()->getColumn());

        $this->assertInstanceOf(SequenceNode::class, $itemsEntry->getValue());
        $firstItem = $itemsEntry->getValue()->getItems()[0];
        $this->assertSame(3, $firstItem->getMetadata()->getLine());
        $this->assertSame(4, $firstItem->getMetadata()->getColumn());
    }

    public function testMetadataSurvivesTagAnchorAndMergeResolverPasses(): void
    {
        $yaml = <<<'YAML'
base: &base
  role: admin
tagged: !!str 42
merged:
  <<: *base
YAML;

        $parser = new YamlParser(config: new ParsingConfig(preserveMetadata: true));
        $parser->parse($yaml);

        $rootMapping = $this->getFirstDocumentRootMapping($parser);

        $baseEntry = $this->findMappingItemByScalarKey($rootMapping, 'base');
        $this->assertSame('base', $baseEntry->getValue()->getMetadata()->getAnchor());
        $this->assertNotNull($baseEntry->getValue()->getMetadata()->getLine());

        $taggedEntry = $this->findMappingItemByScalarKey($rootMapping, 'tagged');
        $this->assertInstanceOf(ScalarNode::class, $taggedEntry->getValue());
        $this->assertSame(42, $taggedEntry->getValue()->getValue());
        $this->assertSame('!!str', $taggedEntry->getValue()->getMetadata()->getTag());
        $this->assertNotNull($taggedEntry->getValue()->getMetadata()->getLine());

        $mergedEntry = $this->findMappingItemByScalarKey($rootMapping, 'merged');
        $this->assertInstanceOf(MappingNode::class, $mergedEntry->getValue());

        $mergedRoleEntry = $this->findMappingItemByScalarKey($mergedEntry->getValue(), 'role');
        $this->assertSame('admin', $mergedRoleEntry->getValue()->getValue());
        $this->assertNotNull($mergedRoleEntry->getKey()->getMetadata()->getLine());
        $this->assertNotNull($mergedRoleEntry->getValue()->getMetadata()->getLine());
    }

    private function getFirstDocumentRootMapping(YamlParser $parser): MappingNode
    {
        $ast = $parser->getLastAst();
        $this->assertInstanceOf(SequenceNode::class, $ast);

        $firstDocument = $ast->getItems()[0] ?? null;
        $this->assertInstanceOf(DocumentNode::class, $firstDocument);

        $root = $firstDocument->getRoot();
        $this->assertInstanceOf(MappingNode::class, $root);

        return $root;
    }

    private function findMappingItemByScalarKey(MappingNode $mapping, string $key): \MaxBeckers\YamlParser\Node\MappingNodeItem
    {
        foreach ($mapping->getMappingNodeItems() as $item) {
            if ($item->getKey() instanceof ScalarNode && $item->getKey()->getValue() === $key) {
                return $item;
            }
        }

        self::fail("Mapping key '{$key}' was not found.");
    }
}
