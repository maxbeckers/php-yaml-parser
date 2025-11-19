<?php

namespace MaxBeckers\YamlParser\Tests;

use MaxBeckers\YamlParser\Exception\LexerException;
use MaxBeckers\YamlParser\YamlParser;
use PHPUnit\Framework\TestCase;

class YamlParserKeyValueTest extends TestCase
{
    private YamlParser $yamlParser;

    protected function setUp(): void
    {
        $this->yamlParser = new YamlParser();
    }

    public function testParseSimpleKeyValueYaml()
    {
        $input = <<<'YAML'
key1: Value1
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(1, $yaml);
        $this->assertArrayHasKey('key1', $yaml);
        $this->assertEquals('Value1', $yaml['key1']);
    }

    public function testParseYaml_withQuotes()
    {
        $input = <<<'YAML'
single: 'text'
double: "text"
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(2, $yaml);
        $this->assertArrayHasKey('single', $yaml);
        $this->assertEquals('text', $yaml['single']);
        $this->assertArrayHasKey('double', $yaml);
        $this->assertEquals('text', $yaml['double']);
    }

    public function testParseYaml_withReservedCharsAt_expectError()
    {
        $input = <<<'YAML'
commercial-at: @text
YAML;

        $this->expectException(LexerException::class);
        $this->expectExceptionMessage('Cannot start plain scalar with \'@\': Reserved indicator in line 1, column 28');
        $this->yamlParser->parse($input);
    }

    public function testParseYaml_withReservedCharsTick_expectError()
    {
        $input = <<<'YAML'
grave-accent: `text
YAML;

        $this->expectException(LexerException::class);
        $this->expectExceptionMessage('Cannot start plain scalar with \'`\': Reserved indicator in line 1, column 26');
        $this->yamlParser->parse($input);
    }

    public function testParseSimpleKeyValueYaml_withMultipleKeys()
    {
        $input = <<<'YAML'
hr:  65    # Home runs
avg: 0.278 # Batting average
rbi: 147   # Runs Batted In
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(3, $yaml);
        $this->assertArrayHasKey('hr', $yaml);
        $this->assertEquals(65, $yaml['hr']);
        $this->assertArrayHasKey('avg', $yaml);
        $this->assertEquals(0.278, $yaml['avg']);
        $this->assertArrayHasKey('rbi', $yaml);
        $this->assertEquals(147, $yaml['rbi']);
    }

    public function testParseSimpleKeyValueYaml_withSubKey()
    {
        $input = <<<'YAML'
key1: 
  subKey1: subValue1
key2: Value2
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(2, $yaml);
        $this->assertArrayHasKey('key1', $yaml);
        $this->assertInstanceOf(\ArrayObject::class, $yaml['key1']);
        $this->assertCount(1, $yaml['key1']);
        $this->assertArrayHasKey('subKey1', $yaml['key1']);
        $this->assertEquals('subValue1', $yaml['key1']['subKey1']);
        $this->assertArrayHasKey('key2', $yaml);
        $this->assertEquals('Value2', $yaml['key2']);
    }

    public function testParseSimpleKeyValueYaml_withSubKeyAndQuotedKey()
    {
        $input = <<<'YAML'
key1: 
  'subKey1': subValue1
key2:
  "subKey2": subValue2
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(2, $yaml);
        $this->assertArrayHasKey('key1', $yaml);
        $this->assertInstanceOf(\ArrayObject::class, $yaml['key1']);
        $this->assertCount(1, $yaml['key1']);
        $this->assertArrayHasKey('subKey1', $yaml['key1']);
        $this->assertEquals('subValue1', $yaml['key1']['subKey1']);
        $this->assertArrayHasKey('key2', $yaml);
        $this->assertInstanceOf(\ArrayObject::class, $yaml['key2']);
        $this->assertCount(1, $yaml['key2']);
        $this->assertArrayHasKey('subKey2', $yaml['key2']);
        $this->assertEquals('subValue2', $yaml['key2']['subKey2']);
    }

    public function testParseSimpleKeyValueYaml_withSubKeyAndQuotedKey_test()
    {
        $input = <<<'YAML'
key1:
  '/key/{test}/example':
    key3: true
  '/key/{test2}/example':
    key4: false
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(1, $yaml);
        $this->assertArrayHasKey('key1', $yaml);
        $this->assertInstanceOf(\ArrayObject::class, $yaml['key1']);
        $this->assertCount(2, $yaml['key1']);
        $this->assertArrayHasKey('/key/{test}/example', $yaml['key1']);
        $this->assertInstanceOf(\ArrayObject::class, $yaml['key1']['/key/{test}/example']);
        $this->assertCount(1, $yaml['key1']['/key/{test}/example']);
        $this->assertArrayHasKey('key3', $yaml['key1']['/key/{test}/example']);
        $this->assertTrue($yaml['key1']['/key/{test}/example']['key3']);
        $this->assertArrayHasKey('/key/{test2}/example', $yaml['key1']);
        $this->assertInstanceOf(\ArrayObject::class, $yaml['key1']['/key/{test2}/example']);
        $this->assertCount(1, $yaml['key1']['/key/{test2}/example']);
        $this->assertArrayHasKey('key4', $yaml['key1']['/key/{test2}/example']);
        $this->assertFalse($yaml['key1']['/key/{test2}/example']['key4']);
    }

    public function testParseYamlWithContentTypes()
    {
        $input = <<<'YAML'
content: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(1, $yaml);
        $this->assertArrayHasKey('content', $yaml);
        $this->assertEquals('text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8', $yaml['content']);
    }

    public function testParseYaml_WithQuotedChars()
    {
        $input = <<<'YAML'
unicode: "Sosa did fine.\u263A"
control: "\b1998\t1999\t2000\n"
hex esc: "\x0d\x0a is \r\n"

single: '"Howdy!" he cried.'
quoted: ' # Not a ''comment''.'
tie-fighter: '|\-*-/|'
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(6, $yaml);
        $this->assertArrayHasKey('unicode', $yaml);
        $this->assertEquals('Sosa did fine.☺', $yaml['unicode']);
        $this->assertArrayHasKey('control', $yaml);
        $this->assertEquals(bin2hex("\x08" . "1998\t1999\t2000\n"), bin2hex($yaml['control']));
        $this->assertArrayHasKey('hex esc', $yaml);
        $this->assertEquals(bin2hex("\r\n is \r\n"), bin2hex($yaml['hex esc']));
        $this->assertArrayHasKey('single', $yaml);
        $this->assertEquals('"Howdy!" he cried.', $yaml['single']);
        $this->assertArrayHasKey('quoted', $yaml);
        $this->assertEquals(' # Not a \'comment\'.', $yaml['quoted']);
        $this->assertArrayHasKey('tie-fighter', $yaml);
        $this->assertEquals('|\-*-/|', $yaml['tie-fighter']);
    }

    public function testParseYaml_WithMultilineFlows()
    {
        $input = <<<'YAML'
plain:
  This unquoted scalar
  spans many lines.

quoted: "So does this
  quoted scalar.\n"
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(2, $yaml);
        $this->assertArrayHasKey('plain', $yaml);
        $this->assertEquals('This unquoted scalar spans many lines.', $yaml['plain']);
        $this->assertArrayHasKey('quoted', $yaml);
        $this->assertEquals("So does this quoted scalar.\n", $yaml['quoted']);
    }

    public function testParseYaml_WithLinePrefixes()
    {
        $input = <<<'YAML'
plain: text
  lines
quoted: "text
  <tab>lines"
block: |
  text
   <tab>lines
YAML;

        $input = str_replace('<tab>', "\t", $input);
        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(3, $yaml);
        $this->assertArrayHasKey('plain', $yaml);
        $this->assertEquals('text lines', $yaml['plain']);
        $this->assertArrayHasKey('quoted', $yaml);
        $this->assertEquals('text lines', $yaml['quoted']);
        $this->assertArrayHasKey('block', $yaml);
        $this->assertEquals("text\n \tlines\n", $yaml['block']);
    }

    public function testParseYaml_WithSeparatedComment()
    {
        $input = <<<'YAML'
key:    # Comment
  value
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(1, $yaml);
        $this->assertArrayHasKey('key', $yaml);
        $this->assertEquals('value', $yaml['key']);
    }

    public function testParseYaml_WithCompletelyEmptyFlowNodes()
    {
        $input = <<<'YAML'
{
  ? foo : ,
  : bar,
}
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(2, $yaml);
        $this->assertArrayHasKey('foo', $yaml);
        $this->assertNull($yaml['foo']);
        $this->assertArrayHasKey('null', $yaml);
        $this->assertEquals('bar', $yaml['null']);
    }

    public function testParseYaml_WithChompingFinalLineBreak()
    {
        $input = <<<'YAML'
strip: |-
  text
clip: |
  text
keep: |+
  text
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(3, $yaml);
        $this->assertArrayHasKey('strip', $yaml);
        $this->assertEquals('text', $yaml['strip']);
        $this->assertArrayHasKey('clip', $yaml);
        $this->assertEquals("text\n", $yaml['clip']);
        $this->assertArrayHasKey('keep', $yaml);
        $this->assertEquals("text\n", $yaml['keep']);
    }

    public function testParseYaml_WithChompingTrailingLines()
    {
        //        $this->markTestSkipped("Comment as part of literal block scalar not yet supported.");
        $input = <<<'YAML'
# Strip
  # Comments:
strip: |-
  # text
  
 # Clip
  # comments:

clip: |
  # text
 
 # Keep
  # comments:

keep: |+
  # text

 # Trail
  # comments.
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(3, $yaml);
        $this->assertArrayHasKey('strip', $yaml);
        $this->assertEquals('# text', $yaml['strip']);
        $this->assertArrayHasKey('clip', $yaml);
        $this->assertEquals("# text\n", $yaml['clip']);
        $this->assertArrayHasKey('keep', $yaml);
        $this->assertEquals("# text\n\n", $yaml['keep']);
    }

    public function testParseYaml_WithEmptyScalarChomping()
    {
        $input = <<<'YAML'
strip: >-

clip: >

keep: |+

YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(3, $yaml);
        $this->assertArrayHasKey('strip', $yaml);
        $this->assertEquals('', $yaml['strip']);
        $this->assertArrayHasKey('clip', $yaml);
        $this->assertEquals('', $yaml['clip']);
        $this->assertArrayHasKey('keep', $yaml);
        $this->assertEquals("\n", $yaml['keep']);
    }

    public function testParseYaml_WithUnquotedMultilineText()
    {
        $input = <<<'YAML'
---
key: this is a multiline text
  that spans several lines
  and ends here.
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(1, $yaml);
        $this->assertArrayHasKey('key', $yaml);
        $this->assertEquals('this is a multiline text that spans several lines and ends here.', $yaml['key']);
    }

    public function testParseYaml_WithUnquotedMultilineTextWithEmptyLine()
    {
        $input = <<<'YAML'
---
key: this is a multiline text

  that spans several lines #with a comment
  
  and ends here.
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(1, $yaml);
        $this->assertArrayHasKey('key', $yaml);
        $this->assertEquals("this is a multiline text\nthat spans several lines\nand ends here.", $yaml['key']);
    }

    public function testParseYaml_WithUnquotedMultilineTextWithDirectLineBreak()
    {
        $input = <<<'YAML'
---
key: 
  this is a multiline text
  that spans several lines #with a comment
  and ends here.
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(1, $yaml);
        $this->assertArrayHasKey('key', $yaml);
        $this->assertEquals('this is a multiline text that spans several lines and ends here.', $yaml['key']);
    }

    public function testParseYamlWithUnquotedMultilineTextWithDirectLineBreakAndEmptyLine()
    {
        $input = <<<'YAML'
---
key:


  this is a multiline text
  
  that spans several lines # with a comment
  
  and ends here.
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertEquals("this is a multiline text\nthat spans several lines\nand ends here.", $yaml['key']);
    }

    public function testParseYaml_withCommaInPlainText()
    {
        $input = <<<'YAML'
key: With a plaintext value that includes a comma, like this and is not handled incorrectly.
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertEquals('With a plaintext value that includes a comma, like this and is not handled incorrectly.', $yaml['key']);
    }

    public function testParseYaml_withNewLineAndComma()
    {
        $input = <<<'YAML'
key1: This is a text with
  a comma in new line, that should be handled correctly.
key2: With a plaintext value that includes a comma, like this and is not handled incorrectly.
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertEquals('This is a text with a comma in new line, that should be handled correctly.', $yaml['key1']);
    }

    public function testParseYaml_withRegex()
    {
        $input = <<<'YAML'
pattern1: ^((19|20|21)\d{6})?$
pattern2: \d{2,4}
pattern3: abc[def]ghi
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertEquals("^((19|20|21)\d{6})?$", $yaml['pattern1']);
        $this->assertEquals("\d{2,4}", $yaml['pattern2']);
        $this->assertEquals('abc[def]ghi', $yaml['pattern3']);
    }

    public function testParseYaml_withBlockMappings()
    {
        $input = <<<'YAML'
block mapping:
 key: value
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertEquals('value', $yaml['block mapping']['key']);
    }

    public function testParseYaml_withCompactBlockMappings()
    {
        $this->markTestSkipped('Compact block mappings not yet supported for explicit key.');
        $input = <<<'YAML'
- sun: yellow
- ? earth: blue
  : moon: white
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(2, $yaml);
        $this->assertEquals('yellow', $yaml[0]['sun']);
        $this->assertEquals('white', $yaml[1]['{"earth":"blue"}']['moon']);
    }
}
