<?php

namespace MaxBeckers\YamlParser\Tests;

use MaxBeckers\YamlParser\Config\ParsingConfig;
use MaxBeckers\YamlParser\YamlParser;
use PHPUnit\Framework\TestCase;

final class MetadataCollectionTest extends TestCase
{
    public function testParserCollectsAccurateLineAndColumnMetadata(): void
    {
        $parser = new YamlParser(config: new ParsingConfig(preserveMetadata: true));
        $parser->parse("name: Jane\nitems:\n  - one\n");

        $provider = $parser->getMetadataProvider();

        self::assertSame(1, $provider->getKeyMetadata('name')?->getLine());
        self::assertSame(0, $provider->getKeyMetadata('name')?->getColumn());
        self::assertSame(1, $provider->getMetadata('name')?->getLine());
        self::assertSame(6, $provider->getMetadata('name')?->getColumn());
        self::assertSame(2, $provider->getKeyMetadata('items')?->getLine());
        self::assertSame(4, $provider->getMetadata('items.0')?->getColumn());
    }

    public function testMetadataSurvivesTagAnchorAndMergeResolverPasses(): void
    {
        $yaml = "base: &base\n  role: admin\ntagged: !!str 42\nmerged:\n  <<: *base\n";

        $parser = new YamlParser(config: new ParsingConfig(preserveMetadata: true));
        $value = $parser->parse($yaml);

        $provider = $parser->getMetadataProvider();

        self::assertSame('base', $provider->getMetadata('base')?->getAnchor());
        self::assertSame('!!str', $provider->getMetadata('tagged')?->getTag());
        self::assertSame('42', $value['tagged']);
        self::assertSame('admin', $value['merged']['role']);
    }
}
