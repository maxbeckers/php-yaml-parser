<?php

namespace MaxBeckers\YamlParser\Tests\Metadata;

use MaxBeckers\YamlParser\Config\ParsingConfig;
use MaxBeckers\YamlParser\Metadata\MetadataProvider;
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
        $this->assertSame(10, $nameValueMetadata->getColumn());

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

    public function testFromAstConstructorIsSupported(): void
    {
        $yaml = "foo: bar\n";

        $parser = new YamlParser(config: new ParsingConfig(preserveMetadata: true));
        $parser->parse($yaml);

        $ast = $parser->getLastAst();
        $this->assertNotNull($ast);

        $provider = new MetadataProvider($ast);
        $this->assertSame(1, $provider->getMetadata('foo')?->getLine());
    }
}
