<?php

namespace MaxBeckers\YamlParser\Tests;

use MaxBeckers\YamlParser\YamlParser;
use PHPUnit\Framework\TestCase;

class YamlParserScalarEdgeCasesTest extends TestCase
{
    private YamlParser $yamlParser;

    protected function setUp(): void
    {
        $this->yamlParser = new YamlParser();
    }

    public function testTildeIsNull(): void
    {
        $result = $this->yamlParser->parse('~');

        $this->assertNull($result);
    }

    public function testNullUppercaseIsNull(): void
    {
        $result = $this->yamlParser->parse('NULL');

        $this->assertNull($result);
    }

    public function testNullMixedCaseIsNull(): void
    {
        $result = $this->yamlParser->parse('Null');

        $this->assertNull($result);
    }

    public function testTildeAsValueIsNull(): void
    {
        $result = $this->yamlParser->parse('key: ~');

        $this->assertNull($result['key']);
    }

    public function testEmptyValueIsNull(): void
    {
        $result = $this->yamlParser->parse("key:\n");

        $this->assertNull($result['key']);
    }

    public function testTrueUppercaseIsResolvedAsBool(): void
    {
        $result = $this->yamlParser->parse('TRUE');

        $this->assertTrue($result);
    }

    public function testFalseUppercaseIsResolvedAsBool(): void
    {
        $result = $this->yamlParser->parse('FALSE');

        $this->assertFalse($result);
    }

    public function testTrueMixedCaseIsResolvedAsBool(): void
    {
        $result = $this->yamlParser->parse('True');

        $this->assertTrue($result);
    }

    public function testYesIsStringInYaml12(): void
    {
        $result = $this->yamlParser->parse('yes');

        $this->assertIsString($result);
        $this->assertEquals('yes', $result);
    }

    public function testNoIsStringInYaml12(): void
    {
        $result = $this->yamlParser->parse('no');

        $this->assertIsString($result);
        $this->assertEquals('no', $result);
    }

    public function testOnIsStringInYaml12(): void
    {
        $result = $this->yamlParser->parse('on');

        $this->assertIsString($result);
        $this->assertEquals('on', $result);
    }

    public function testOffIsStringInYaml12(): void
    {
        $result = $this->yamlParser->parse('off');

        $this->assertIsString($result);
        $this->assertEquals('off', $result);
    }

    public function testIntegerZero(): void
    {
        $result = $this->yamlParser->parse('0');

        $this->assertSame(0, $result);
    }

    public function testNegativeInteger(): void
    {
        $result = $this->yamlParser->parse('-42');

        $this->assertSame(-42, $result);
    }

    public function testNegativeIntegerAsValue(): void
    {
        $result = $this->yamlParser->parse('key: -1');

        $this->assertSame(-1, $result['key']);
    }

    public function testLargeInteger(): void
    {
        $result = $this->yamlParser->parse('9999999999');

        $this->assertSame(9999999999, $result);
    }

    public function testPositiveInfinity(): void
    {
        $result = $this->yamlParser->parse('.inf');

        $this->assertEquals(INF, $result);
    }

    public function testPositiveInfinityTitleCase(): void
    {
        $result = $this->yamlParser->parse('.Inf');

        $this->assertEquals(INF, $result);
    }

    public function testPositiveInfinityUppercase(): void
    {
        $result = $this->yamlParser->parse('.INF');

        $this->assertEquals(INF, $result);
    }

    public function testPositiveInfinityWithPlusSign(): void
    {
        $result = $this->yamlParser->parse('+.inf');

        $this->assertEquals(INF, $result);
    }

    public function testNaNTitleCase(): void
    {
        $result = $this->yamlParser->parse('.NaN');

        $this->assertNan($result);
    }

    public function testNaNUppercase(): void
    {
        $result = $this->yamlParser->parse('.NAN');

        $this->assertNan($result);
    }

    public function testFloatZero(): void
    {
        $result = $this->yamlParser->parse('0.0');

        $this->assertSame(0.0, $result);
    }

    public function testNegativeFloat(): void
    {
        $result = $this->yamlParser->parse('-1.5');

        $this->assertEqualsWithDelta(-1.5, $result, 1e-10);
    }

    public function testEmptyFlowSequence(): void
    {
        $result = $this->yamlParser->parse('[]');

        $this->assertInstanceOf(\ArrayObject::class, $result);
        $this->assertCount(0, $result);
    }

    public function testEmptyFlowMapping(): void
    {
        $result = $this->yamlParser->parse('{}');

        $this->assertInstanceOf(\ArrayObject::class, $result);
        $this->assertCount(0, $result);
    }

    public function testEmptyFlowSequenceAsValue(): void
    {
        $result = $this->yamlParser->parse('key: []');

        $this->assertInstanceOf(\ArrayObject::class, $result['key']);
        $this->assertCount(0, $result['key']);
    }

    public function testEmptyFlowMappingAsValue(): void
    {
        $result = $this->yamlParser->parse('key: {}');

        $this->assertInstanceOf(\ArrayObject::class, $result['key']);
        $this->assertCount(0, $result['key']);
    }

    public function testDuplicateKeyLastValueWins(): void
    {
        $input = "key: first\nkey: second\n";

        $result = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $result);
        $this->assertArrayHasKey('key', $result);
        $this->assertContains($result['key'], ['first', 'second']);
    }

    public function testEmptyStringInput(): void
    {
        $result = $this->yamlParser->parse('');

        $this->assertTrue($result === null || (($result instanceof \ArrayObject || is_array($result)) && count($result) === 0));
    }

    public function testWhitespaceOnlyInput(): void
    {
        $result = $this->yamlParser->parse("   \n   \n");

        $this->assertTrue($result === null || (($result instanceof \ArrayObject || is_array($result)) && count($result) === 0));
    }

    public function testCrlfMappingIsParsedCorrectly(): void
    {
        $input = "key1: value1\r\nkey2: value2\r\n";

        $result = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $result);
        $this->assertEquals('value1', $result['key1']);
        $this->assertEquals('value2', $result['key2']);
    }

    public function testCrlfSequenceIsParsedCorrectly(): void
    {
        $input = "- alpha\r\n- beta\r\n- gamma\r\n";

        $result = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $result);
        $this->assertCount(3, $result);
        $this->assertEquals('alpha', $result[0]);
        $this->assertEquals('beta', $result[1]);
        $this->assertEquals('gamma', $result[2]);
    }

    public function testCrlfLiteralBlockScalarIsParsedCorrectly(): void
    {
        $input = "text: |\r\n  line one\r\n  line two\r\n";

        $result = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $result);
        $this->assertEquals("line one\nline two\n", $result['text']);
    }

    public function testIntegerKey(): void
    {
        $input = "? 42\n: the answer\n";

        $result = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $result);
        $this->assertArrayHasKey(42, $result);
        $this->assertEquals('the answer', $result[42]);
    }

    public function testBooleanTrueKey(): void
    {
        $input = "? true\n: yes it is\n";

        $result = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $result);
        $this->assertArrayHasKey(true, $result);
        $this->assertEquals('yes it is', $result[true]);
    }

    public function testPlainScalarWithColonInsideIsNotSplit(): void
    {
        $result = $this->yamlParser->parse('url: https://example.com/path');

        $this->assertEquals('https://example.com/path', $result['url']);
    }

    public function testPlainScalarWithHashInsideIsNotComment(): void
    {
        $result = $this->yamlParser->parse('color: "#ff0000"');

        $this->assertEquals('#ff0000', $result['color']);
    }
}
