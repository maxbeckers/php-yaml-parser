<?php

namespace MaxBeckers\YamlParser\Tests;

use MaxBeckers\YamlParser\YamlParser;
use PHPUnit\Framework\TestCase;

class YamlParserTimestampTest extends TestCase
{
    private YamlParser $yamlParser;

    protected function setUp(): void
    {
        $this->yamlParser = new YamlParser();
    }

    public function testParseSimpleKeyValueYaml()
    {
        $input = <<<'YAML'
canonical: 2001-12-15T02:59:43.1Z
iso8601: 2001-12-14t21:59:43.10-05:00
spaced: 2001-12-14 21:59:43.10 -5
date: 2002-12-14
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(4, $yaml);
        $this->assertArrayHasKey('canonical', $yaml);
        $this->assertEquals('2001-12-15T02:59:43.1Z', $yaml['canonical']);
        $this->assertArrayHasKey('iso8601', $yaml);
        $this->assertEquals('2001-12-14t21:59:43.10-05:00', $yaml['iso8601']);
        $this->assertArrayHasKey('spaced', $yaml);
        $this->assertEquals('2001-12-14 21:59:43.10 -5', $yaml['spaced']);
        $this->assertArrayHasKey('date', $yaml);
        $this->assertEquals('2002-12-14', $yaml['date']);
    }
}
