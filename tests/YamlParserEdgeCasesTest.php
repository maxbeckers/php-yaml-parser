<?php

namespace MaxBeckers\YamlParser\Tests;

use MaxBeckers\YamlParser\YamlParser;
use PHPUnit\Framework\TestCase;

/**
 * Tests for edge cases fixed in the parser.
 */
class YamlParserEdgeCasesTest extends TestCase
{
    private YamlParser $yamlParser;

    protected function setUp(): void
    {
        $this->yamlParser = new YamlParser();
    }

    public function testUnknownDirectiveIsIgnored(): void
    {
        $input = "%FOO bar baz\n--- value";

        $result = $this->yamlParser->parse($input);

        $this->assertEquals('value', $result);
    }

    public function testUnknownDirectiveWithCommentIsIgnored(): void
    {
        $input = "%FOO bar baz # comment\n--- value";

        $result = $this->yamlParser->parse($input);

        $this->assertEquals('value', $result);
    }

    public function testUnsupportedYamlVersionFallsBackTo12(): void
    {
        $input = "%YAML 1.3\n--- value";

        $result = $this->yamlParser->parse($input);

        $this->assertEquals('value', $result);
    }

    public function testYamlDirectiveWithInlineComment(): void
    {
        $input = "%YAML 1.1 # comment\n--- value";

        $result = $this->yamlParser->parse($input);

        $this->assertEquals('value', $result);
    }

    public function testDoubleQuotedTabEscapeCharacter(): void
    {
        $yaml = '"hello\\tworld"';

        $result = $this->yamlParser->parse($yaml);

        $this->assertEquals("hello\tworld", $result);
    }

    public function testDoubleQuotedLiteralTabCharacter(): void
    {
        $yaml = "\"hello\\\tworld\"";

        $result = $this->yamlParser->parse($yaml);

        $this->assertEquals("hello\tworld", $result);
    }

    public function testEmptyKeyInBlockMapping(): void
    {
        $input = ": value\n";

        $result = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $result);
        $this->assertArrayHasKey('null', $result);
        $this->assertEquals('value', $result['null']);
    }

    public function testEmptyKeyInFlowMapping(): void
    {
        $input = "{ : value }\n";

        $result = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $result);
        $this->assertArrayHasKey('null', $result);
        $this->assertEquals('value', $result['null']);
    }

    public function testEmptyKeyAndValueInBlockMapping(): void
    {
        $input = ":\n";

        $result = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $result);
        $this->assertArrayHasKey('null', $result);
        $this->assertNull($result['null']);
    }

    public function testEmptyKeyAtStartOfFlowMapping(): void
    {
        $input = "{ : first, key: second }\n";

        $result = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $result);
        $this->assertEquals('first', $result['null']);
        $this->assertEquals('second', $result['key']);
    }

    public function testEmptyKeyInSequenceItem(): void
    {
        $input = "- :\n";

        $result = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $result);
        $this->assertInstanceOf(\ArrayObject::class, $result[0]);
        $this->assertArrayHasKey('null', $result[0]);
        $this->assertNull($result[0]['null']);
    }

    public function testMultilineFlowMapping(): void
    {
        $input = "key: {\n    a: 1,\n    b: 2\n  }";

        $result = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $result);
        $this->assertEquals(1, $result['key']['a']);
        $this->assertEquals(2, $result['key']['b']);
    }

    public function testMultilineFlowMappingClosingBraceOnOwnLine(): void
    {
        $input = "{\n    hr: 63,\n    avg: 0.288\n}";

        $result = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $result);
        $this->assertEquals(63, $result['hr']);
        $this->assertEqualsWithDelta(0.288, $result['avg'], 0.0001);
    }

    public function testMultilineDoubleQuotedKeyInFlowMapping(): void
    {
        $input = "{ \"multi\n  line\": value }";

        $result = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $result);
        $this->assertEquals('value', $result['multi line']);
    }

    public function testInlineEnumFlowDoesNotNestSiblingPropertiesOrSchemas(): void
    {
        $input = <<<'YAML'
openapi: 3.0.3
components:
  schemas:
    SetFlagEffect:
      type: object
      properties:
        type:
          type: string
          enum: [set_flag]
        flag:
          type: string
        value:
          oneOf:
            - type: boolean
            - type: number
    NextSchema:
      type: object
YAML;

        $result = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $result);
        $schemas = $result['components']['schemas'];
        $this->assertArrayHasKey('SetFlagEffect', $schemas);
        $this->assertArrayHasKey('NextSchema', $schemas);

        $props = $schemas['SetFlagEffect']['properties'];
        $this->assertArrayHasKey('type', $props);
        $this->assertArrayHasKey('flag', $props);
        $this->assertArrayHasKey('value', $props);
    }

    public function testOneOfDiscriminatorSectionDoesNotHideFollowingSchemas(): void
    {
        $input = <<<'YAML'
openapi: 3.0.3
components:
  schemas:
    DialogueEffect:
      oneOf:
        - $ref: '#/components/schemas/SetFlagEffect'
      discriminator:
        propertyName: type
        mapping:
          set_flag: '#/components/schemas/SetFlagEffect'
    SetFlagEffect:
      type: object
      required: [type, flag]
      properties:
        type:
          type: string
          enum: [set_flag]
        flag:
          type: string
    DialogueCondition:
      oneOf:
        - $ref: '#/components/schemas/FlagCondition'
      discriminator:
        propertyName: type
        mapping:
          flag: '#/components/schemas/FlagCondition'
    FlagCondition:
      type: object
      properties:
        type:
          type: string
          enum: [flag]
YAML;

        $result = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $result);
        $schemas = $result['components']['schemas'];
        $this->assertArrayHasKey('DialogueEffect', $schemas);
        $this->assertArrayHasKey('SetFlagEffect', $schemas);
        $this->assertArrayHasKey('DialogueCondition', $schemas);
        $this->assertArrayHasKey('FlagCondition', $schemas);
    }
}
