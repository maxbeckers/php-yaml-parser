<?php

namespace MaxBeckers\YamlParser\Tests;

use MaxBeckers\YamlParser\YamlParser;
use PHPUnit\Framework\TestCase;

class YamlParserNumbersTest extends TestCase
{
    private YamlParser $yamlParser;

    protected function setUp(): void
    {
        $this->yamlParser = new YamlParser();
    }

    public function testParseYaml_withNumbers()
    {
        $input = <<<'YAML'
canonical: 12345
decimal: +12345
octal: 0o14
hexadecimal: 0xC
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(4, $yaml);
        $this->assertArrayHasKey('canonical', $yaml);
        $this->assertEquals(12345, $yaml['canonical']);
        $this->assertArrayHasKey('decimal', $yaml);
        $this->assertEquals(12345, $yaml['decimal']);
        $this->assertArrayHasKey('octal', $yaml);
        $this->assertEquals(12, $yaml['octal']);
        $this->assertArrayHasKey('hexadecimal', $yaml);
        $this->assertEquals(12, $yaml['hexadecimal']);

    }

    public function testParseYaml_withFloatingPoint()
    {
        $input = <<<'YAML'
canonical: 1.23015e+3
exponential: 12.3015e+02
fixed: 1230.15
negative infinity: -.inf
not a number: .nan
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(5, $yaml);
        $this->assertArrayHasKey('canonical', $yaml);
        $this->assertEquals(1230.15, $yaml['canonical']);
        $this->assertArrayHasKey('exponential', $yaml);
        $this->assertEquals(1230.15, $yaml['exponential']);
        $this->assertArrayHasKey('fixed', $yaml);
        $this->assertEquals(1230.15, $yaml['fixed']);
        $this->assertArrayHasKey('negative infinity', $yaml);
        $this->assertEquals(-INF, $yaml['negative infinity']);
        $this->assertArrayHasKey('not a number', $yaml);
        $this->assertNan($yaml['not a number']);

    }

    public function testParseYaml_withSpecialValueHandling()
    {
        $input = <<<'YAML'
null:
booleans: [ true, false ]
string: '012345'
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(3, $yaml);
        $this->assertArrayHasKey('null', $yaml);
        $this->assertNull($yaml['null']);
        $this->assertArrayHasKey('booleans', $yaml);
        $this->assertInstanceOf(\ArrayObject::class, $yaml['booleans']);
        $this->assertCount(2, $yaml['booleans']);
        $this->assertTrue($yaml['booleans'][0]);
        $this->assertFalse($yaml['booleans'][1]);
        $this->assertArrayHasKey('string', $yaml);
        $this->assertEquals('012345', $yaml['string']);

    }
}
