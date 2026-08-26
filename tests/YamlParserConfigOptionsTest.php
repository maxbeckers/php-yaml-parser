<?php

namespace MaxBeckers\YamlParser\Tests;

use MaxBeckers\YamlParser\Api\NodeInterface;
use MaxBeckers\YamlParser\Config\ParsingConfig;
use MaxBeckers\YamlParser\Exception\ParserException;
use MaxBeckers\YamlParser\Node\DocumentNode;
use MaxBeckers\YamlParser\Node\MappingNode;
use MaxBeckers\YamlParser\Node\SequenceNode;
use MaxBeckers\YamlParser\Node\YamlNode;
use MaxBeckers\YamlParser\YamlParser;
use PHPUnit\Framework\TestCase;

final class YamlParserConfigOptionsTest extends TestCase
{
    public function testMaxFileSizeLimitThrowsWhenFileIsTooLarge(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'yaml-max-size-');
        file_put_contents($tmpFile, "key: value\n");

        try {
            $yamlParser = new YamlParser(config: new ParsingConfig(maxFileSize: 4));

            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('exceeds configured limit');

            $yamlParser->parseFile($tmpFile);
        } finally {
            @unlink($tmpFile);
        }
    }

    public function testMaxDepthLimitThrowsForDeeplyNestedYaml(): void
    {
        $yaml = <<<'YAML'
a:
  b:
    c:
      d: value
YAML;

        $yamlParser = new YamlParser(config: new ParsingConfig(maxDepth: 3));

        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Maximum parsing depth of 3 exceeded');

        $yamlParser->parse($yaml);
    }

    public function testStrictModeTrueRejectsMalformedNestedMappingInScalarValue(): void
    {
        $yaml = "a: b: c\n";

        $strictParser = new YamlParser(config: new ParsingConfig(strictMode: true));
        $this->expectException(ParserException::class);
        $strictParser->parse($yaml);
    }

    public function testStrictModeFalseParsesMalformedNestedMappingInScalarValue(): void
    {
        $yaml = "a: b: c\n";

        $lenientParser = new YamlParser(config: new ParsingConfig(strictMode: false));
        $result = $lenientParser->parse($yaml);

        $this->assertNotNull($result);
    }

    public function testLazyResolutionStillResolvesAliasesWhenNeeded(): void
    {
        $yaml = <<<'YAML'
base: &base
  value: 42
copy: *base
YAML;

        $yamlParser = new YamlParser(config: new ParsingConfig(lazyResolution: true, returnPlainArrays: true));
        $result = $yamlParser->parse($yaml);

        $this->assertIsArray($result);
        $this->assertSame(42, $result['copy']['value']);
    }

    public function testPreserveMetadataStoresAstWithSourcePositions(): void
    {
        $yaml = "key: value\n";

        $yamlParser = new YamlParser(config: new ParsingConfig(preserveMetadata: true));
        $yamlParser->parse($yaml);

        $ast = $yamlParser->getLastAst();
        $this->assertInstanceOf(NodeInterface::class, $ast);
        $this->assertTrue($this->containsNodeWithPosition($ast));
    }

    private function containsNodeWithPosition(NodeInterface $node): bool
    {
        $metadata = $node->getMetadata();
        if ($metadata->getLine() !== null && $metadata->getColumn() !== null) {
            return true;
        }

        if ($node instanceof YamlNode) {
            foreach ($node->getDocuments() as $document) {
                if ($this->containsNodeWithPosition($document)) {
                    return true;
                }
            }
        }

        if ($node instanceof DocumentNode) {
            return $this->containsNodeWithPosition($node->getRoot());
        }

        if ($node instanceof SequenceNode) {
            foreach ($node->getItems() as $item) {
                if ($this->containsNodeWithPosition($item)) {
                    return true;
                }
            }
        }

        if ($node instanceof MappingNode) {
            foreach ($node->getMappingNodeItems() as $item) {
                if ($this->containsNodeWithPosition($item->getKey()) || $this->containsNodeWithPosition($item->getValue())) {
                    return true;
                }
            }
        }

        return false;
    }
}
