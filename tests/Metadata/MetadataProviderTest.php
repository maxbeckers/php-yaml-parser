<?php

namespace MaxBeckers\YamlParser\Tests\Metadata;

use MaxBeckers\YamlParser\Config\ParsingConfig;
use MaxBeckers\YamlParser\YamlParser;
use PHPUnit\Framework\TestCase;

final class MetadataProviderTest extends TestCase
{
    public function testGetMetadataAndKeyMetadataForPath(): void
    {
        $yaml = <<<'YAML'
name: Jane
items:
  - one
YAML;

        $parser = new YamlParser(config: new ParsingConfig(preserveMetadata: true));
        $parser->parse($yaml);

        $provider = $parser->getMetadataProvider();

        $nameValueMetadata = $provider->getMetadata('name');
        $this->assertNotNull($nameValueMetadata);
        $this->assertSame(1, $nameValueMetadata->getLine());
        $this->assertSame(6, $nameValueMetadata->getColumn());

        $nameKeyMetadata = $provider->getKeyMetadata('name');
        $this->assertNotNull($nameKeyMetadata);
        $this->assertSame(1, $nameKeyMetadata->getLine());
        $this->assertSame(0, $nameKeyMetadata->getColumn());

        $firstItemMetadata = $provider->getMetadata(['items', 0]);
        $this->assertNotNull($firstItemMetadata);
        $this->assertSame(3, $firstItemMetadata->getLine());
        $this->assertSame(4, $firstItemMetadata->getColumn());
    }
    public function testGetValueWithMetadataSupportsArrayObjectAndPlainArrays(): void
    {
        $yaml = <<<'YAML'
name: Jane
items:
  - one
YAML;

        $arrayObjectParser = new YamlParser(config: new ParsingConfig(preserveMetadata: true));
        $arrayObjectResult = $arrayObjectParser->parse($yaml);
        $arrayObjectValue = $arrayObjectParser->getMetadataProvider()->getValueWithMetadata($arrayObjectResult, 'items.0');

        $this->assertSame('one', $arrayObjectValue->getValue());
        $this->assertTrue($arrayObjectValue->hasMetadata());
        $this->assertSame(3, $arrayObjectValue->getMetadata()?->getLine());

        $plainArrayParser = new YamlParser(config: new ParsingConfig(preserveMetadata: true, returnPlainArrays: true));
        $plainArrayResult = $plainArrayParser->parsePlainArray($yaml);
        $plainArrayValue = $plainArrayParser->getMetadataProvider()->getValueWithMetadata($plainArrayResult, 'items.0');

        $this->assertSame('one', $plainArrayValue->getValue());
        $this->assertTrue($plainArrayValue->hasMetadata());
        $this->assertSame(3, $plainArrayValue->getMetadata()?->getLine());
    }

    public function testProviderCanKeepDocumentWrapperWhenDisabled(): void
    {
        $yaml = "foo: bar\n";

        $parser = new YamlParser(config: new ParsingConfig(preserveMetadata: true));
        $parser->parse($yaml, stripWrapperOnSingleItem: false);

        $provider = $parser->getMetadataProvider(stripWrapperOnSingleItem: false);
        $wrappedMetadata = $provider->getMetadata([0, 'foo']);

        $this->assertNotNull($wrappedMetadata);
        $this->assertSame(1, $wrappedMetadata->getLine());
    }

    public function testFlowMappingMetadataIsAvailable(): void
    {
        $parser = new YamlParser(config: new ParsingConfig(preserveMetadata: true));
        $parser->parse('{a: 1, b: 2}');

        $provider = $parser->getMetadataProvider();

        $this->assertNotNull($provider->getMetadata('a'));
        $this->assertNotNull($provider->getKeyMetadata('b'));
    }

    public function testMultiDocumentMetadataUsesDocumentIndexes(): void
    {
        $parser = new YamlParser(config: new ParsingConfig(preserveMetadata: true));
        $parser->parse("---\nfoo: 1\n---\nbar: 2\n");

        $provider = $parser->getMetadataProvider(false);

        $this->assertNotNull($provider->getMetadata([0, 'foo']));
        $this->assertNotNull($provider->getMetadata([1, 'bar']));
    }

    public function testCustomTagMetadataIsCaptured(): void
    {
        $parser = new YamlParser(config: new ParsingConfig(preserveMetadata: true));
        $parser->parse('custom: !example value');

        $this->assertSame('!example', $parser->getMetadataProvider()->getMetadata('custom')?->getTag());
    }

    public function testDuplicateKeyAtDifferentNestingLevelsResolvesRealPosition(): void
    {
        $yaml = <<<'YAML'
name: Outer
nested:
  name: Inner
YAML;

        $parser = new YamlParser(config: new ParsingConfig(preserveMetadata: true));
        $parser->parse($yaml);

        $provider = $parser->getMetadataProvider();

        $outerNameMetadata = $provider->getMetadata('name');
        $this->assertNotNull($outerNameMetadata);
        $this->assertSame(1, $outerNameMetadata->getLine());

        $innerNameMetadata = $provider->getMetadata(['nested', 'name']);
        $this->assertNotNull($innerNameMetadata);
        $this->assertSame(3, $innerNameMetadata->getLine());
    }

    public function testSiblingSequenceItemsDoNotShareAGlobalItemCounter(): void
    {
        $yaml = <<<'YAML'
items:
  - a
  - b
more:
  - x
  - y
  - z
YAML;

        $parser = new YamlParser(config: new ParsingConfig(preserveMetadata: true));
        $parser->parse($yaml);

        $provider = $parser->getMetadataProvider();

        $this->assertSame(2, $provider->getMetadata(['items', 0])->getLine());
        $this->assertSame(3, $provider->getMetadata(['items', 1])->getLine());
        $this->assertSame(5, $provider->getMetadata(['more', 0])->getLine());
        $this->assertSame(6, $provider->getMetadata(['more', 1])->getLine());
        $this->assertSame(7, $provider->getMetadata(['more', 2])->getLine());
    }

    public function testRepeatedScalarValueAcrossSequencesResolvesItsOwnOccurrence(): void
    {
        $yaml = <<<'YAML'
list1:
  - dup
  - other
list2:
  - dup
YAML;

        $parser = new YamlParser(config: new ParsingConfig(preserveMetadata: true));
        $parser->parse($yaml);

        $provider = $parser->getMetadataProvider();

        $this->assertSame(2, $provider->getMetadata(['list1', 0])->getLine());
        $this->assertSame(5, $provider->getMetadata(['list2', 0])->getLine());
    }

    public function testNullMappingValueKeepsItsOwnSourcePosition(): void
    {
        $parser = new YamlParser(config: new ParsingConfig(preserveMetadata: true));
        $parser->parse("empty:\nnext: value\n");

        $metadata = $parser->getMetadataProvider()->getMetadata('empty');

        $this->assertNotNull($metadata);
        $this->assertSame(1, $metadata->getLine());
        $this->assertSame(6, $metadata->getColumn());
    }
}
