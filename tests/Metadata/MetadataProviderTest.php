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

    public function testPathLookupsHandleMissingKeysAndInvalidSequenceIndexes(): void
    {
        $parser = new YamlParser(config: new ParsingConfig(preserveMetadata: true));
        $result = $parser->parse("users:\n  - name: Jane\n");
        $provider = $parser->getMetadataProvider();

        $this->assertTrue($provider->hasMetadata('users..0.name'));
        $this->assertSame(
            $provider->getMetadata('users.0.name'),
            $provider->getMetadata(['users', '0', 'name']),
        );
        $this->assertFalse($provider->hasMetadata('users.missing'));
        $this->assertNull($provider->getMetadata('users.missing'));
        $this->assertNull($provider->getMetadata('users.name'));
        $this->assertNull($provider->getKeyMetadata(['users', 0]));
        $this->assertNull($provider->getKeyMetadata('users.missing'));
        $this->assertNull($provider->getValueWithMetadata($result, 'users.missing')->getValue());
        $this->assertFalse($provider->getValueWithMetadata($result, 'users.missing')->hasMetadata());
    }

    public function testNodeKindsDistinguishEmptyCollectionsWithoutChangingParsedValues(): void
    {
        $yaml = <<<'YAML'
emptyObject: {}
emptyArray: []
objects:
  - {}
  - key: value
scalars: [null, 42]
anchor: &empty {}
alias: *empty
YAML;
        foreach ([false, true] as $plain) {
            $parser = new YamlParser(config: new ParsingConfig(preserveMetadata: true, returnPlainArrays: $plain));
            $value = $parser->parse($yaml);
            $provider = $parser->getMetadataProvider();
            $this->assertSame('mapping', $provider->getNodeKind());
            $this->assertSame('mapping', $provider->getNodeKind('emptyObject'));
            $this->assertSame('sequence', $provider->getNodeKind('emptyArray'));
            $this->assertSame('sequence', $provider->getNodeKind('objects'));
            $this->assertSame('mapping', $provider->getNodeKind(['objects', 0]));
            $this->assertSame('mapping', $provider->getNodeKind(['objects', 1]));
            $this->assertSame('scalar', $provider->getNodeKind(['objects', 1, 'key']));
            $this->assertSame('scalar', $provider->getNodeKind(['scalars', 0]));
            $this->assertSame('mapping', $provider->getNodeKind('anchor'));
            $this->assertNull($provider->getNodeKind('alias'));
            $this->assertNull($provider->getNodeKind('missing'));
            if ($plain) {
                $this->assertSame([], $value['emptyArray']);
            } else {
                $this->assertInstanceOf(\ArrayObject::class, $value['emptyArray']);
                $this->assertSame([], $value['emptyArray']->getArrayCopy());
            }
        }
    }

    public function testNodeKindsRespectDocumentWrappers(): void
    {
        $parser = new YamlParser(config: new ParsingConfig(preserveMetadata: true));
        $parser->parse("---\na: []\n---\n{}\n", false);
        $provider = $parser->getMetadataProvider(false);
        $this->assertSame('sequence', $provider->getNodeKind());
        $this->assertSame('mapping', $provider->getNodeKind([0]));
        $this->assertSame('sequence', $provider->getNodeKind([0, 'a']));
        $this->assertSame('mapping', $provider->getNodeKind([1]));
    }

    public function testAliasesRemainUnknownAndExposeTheirSourceMetadata(): void
    {
        $yaml = <<<'YAML'
base: &base [1, 2]
aliases:
  - *base
  - *base
YAML;
        $parser = new YamlParser(config: new ParsingConfig(preserveMetadata: true));
        $parser->parse($yaml);
        $provider = $parser->getMetadataProvider();
        $this->assertSame('sequence', $provider->getNodeKind('base'));
        foreach ([0, 1] as $index) {
            $this->assertNull($provider->getNodeKind(['aliases', $index]));
            $this->assertSame('base', $provider->getMetadata(['aliases', $index])?->getAlias());
            $this->assertNull($provider->getNodeKind(['aliases', $index, 0]));
        }
    }
}
