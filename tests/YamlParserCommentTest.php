<?php

namespace MaxBeckers\YamlParser\Tests;

use MaxBeckers\YamlParser\YamlParser;
use PHPUnit\Framework\TestCase;

class YamlParserCommentTest extends TestCase
{
    private YamlParser $yamlParser;

    protected function setUp(): void
    {
        $this->yamlParser = new YamlParser();
    }

    public function testParseYaml_withSingleDocumentWithTwoComments()
    {
        $input = <<<'YAML'
---
hr: # 1998 hr ranking
- Mark McGwire
- Sammy Sosa
# 1998 rbi ranking
rbi:
- Sammy Sosa
- Ken Griffey
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(2, $yaml);
        $this->assertArrayHasKey('hr', $yaml);
        $this->assertCount(2, $yaml['hr']);
        $this->assertEquals('Mark McGwire', $yaml['hr'][0]);
        $this->assertEquals('Sammy Sosa', $yaml['hr'][1]);
        $this->assertArrayHasKey('rbi', $yaml);
        $this->assertCount(2, $yaml['rbi']);
        $this->assertEquals('Sammy Sosa', $yaml['rbi'][0]);
        $this->assertEquals('Ken Griffey', $yaml['rbi'][1]);
    }

    public function testParseYaml_withSingleDocumentWithAlias()
    {
        $input = <<<'YAML'
---
hr:
- Mark McGwire
# Following node labeled SS
- &SS Sammy Sosa
rbi:
- *SS # Subsequent occurrence
- Ken Griffey
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(2, $yaml);
        $this->assertArrayHasKey('hr', $yaml);
        $this->assertCount(2, $yaml['hr']);
        $this->assertEquals('Mark McGwire', $yaml['hr'][0]);
        $this->assertEquals('Sammy Sosa', $yaml['hr'][1]);
        $this->assertArrayHasKey('rbi', $yaml);
        $this->assertCount(2, $yaml['rbi']);
        $this->assertEquals('Sammy Sosa', $yaml['rbi'][0]);
        $this->assertEquals('Ken Griffey', $yaml['rbi'][1]);
    }

    public function testParseYaml_withCommentOnly()
    {
        $input = <<<'YAML'
# Comment only.
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(0, $yaml);
    }

    public function testParseYaml_withCommentOnlyWithEmptyLines()
    {
        $input = <<<'YAML'
  # Comment
   

YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(0, $yaml);
    }

    public function testParseYaml_withMultiLineComments()
    {
        $input = <<<'YAML'
key:    # Comment
        # lines
  value

YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(1, $yaml);
        $this->assertArrayHasKey('key', $yaml);
        $this->assertEquals('value', $yaml['key']);
    }
}
