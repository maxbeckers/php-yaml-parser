<?php

namespace MaxBeckers\YamlParser\Tests;

use MaxBeckers\YamlParser\YamlParser;
use PHPUnit\Framework\Attributes\DataProvider;
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
        $this->assertSame(12345, $yaml['canonical']);
        $this->assertArrayHasKey('decimal', $yaml);
        $this->assertSame(12345, $yaml['decimal']);
        $this->assertArrayHasKey('octal', $yaml);
        $this->assertSame(12, $yaml['octal']);
        $this->assertArrayHasKey('hexadecimal', $yaml);
        $this->assertSame(12, $yaml['hexadecimal']);

    }

    #[DataProvider('provideBasePrefixedScalars')]
    public function testBasePrefixedScalarTypes(string $input, int|string $expected): void
    {
        $this->assertSame($expected, $this->yamlParser->parse($input));
        $this->assertSame($expected, $this->yamlParser->parse('value: ' . $input)['value']);
        $this->assertSame($expected, $this->yamlParser->parse('[' . $input . ']')[0]);
        $this->assertSame([$expected], $this->yamlParser->parsePlainArray('[' . $input . ']'));
    }

    public static function provideBasePrefixedScalars(): array
    {
        return [
            'hexadecimal' => ['0x1F', 31],
            'uppercase hexadecimal' => ['0X1f', 31],
            'hexadecimal zero' => ['0x0', 0],
            'hexadecimal leading zeros' => ['0x001F', 31],
            'hexadecimal integer limit' => ['0x' . dechex(PHP_INT_MAX), PHP_INT_MAX],
            'octal' => ['0o37', 31],
            'uppercase octal' => ['0O37', 31],
            'octal zero' => ['0o0', 0],
            'octal integer limit' => ['0o' . decoct(PHP_INT_MAX), PHP_INT_MAX],
            'single quoted hexadecimal' => ["'0x1F'", '0x1F'],
            'double quoted hexadecimal' => ['"0x1F"', '0x1F'],
            'quoted octal' => ["'0o37'", '0o37'],
            'invalid hexadecimal' => ['0x1G', '0x1G'],
            'invalid octal' => ['0o38', '0o38'],
        ];
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
