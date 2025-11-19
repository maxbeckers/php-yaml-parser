<?php

namespace MaxBeckers\YamlParser\Tests;

use MaxBeckers\YamlParser\Exception\LexerException;
use MaxBeckers\YamlParser\Exception\ParserException;
use MaxBeckers\YamlParser\YamlParser;
use PHPUnit\Framework\TestCase;

class YamlParserSequenceTest extends TestCase
{
    private YamlParser $yamlParser;

    protected function setUp(): void
    {
        $this->yamlParser = new YamlParser();
    }

    public function testParseYaml_withListOnRootLevel()
    {
        $input = <<<'YAML'
- Mark McGwire
- Sammy Sosa
- Ken Griffey
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(3, $yaml);
        $this->assertEquals('Mark McGwire', $yaml[0]);
        $this->assertEquals('Sammy Sosa', $yaml[1]);
        $this->assertEquals('Ken Griffey', $yaml[2]);
    }

    public function testParseYaml_withListOnRootLevelObjects()
    {
        $input = <<<'YAML'
-
  name: Mark McGwire
  hr:   65
  avg:  0.278
-
  name: Sammy Sosa
  hr:   63
  avg:  0.288
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(2, $yaml);
        $this->assertEquals('Mark McGwire', $yaml[0]['name']);
        $this->assertEquals(65, $yaml[0]['hr']);
        $this->assertEquals(0.278, $yaml[0]['avg']);
        $this->assertEquals('Sammy Sosa', $yaml[1]['name']);
        $this->assertEquals(63, $yaml[1]['hr']);
        $this->assertEquals(0.288, $yaml[1]['avg']);
    }

    public function testParseYaml_withSequenceOfSequences()
    {
        $input = <<<'YAML'
- [name        , hr, avg  ]
- [Mark McGwire, 65, 0.278]
- [Sammy Sosa  , 63, 0.288]
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(3, $yaml);
        $this->assertEquals('name', $yaml[0][0]);
        $this->assertEquals('hr', $yaml[0][1]);
        $this->assertEquals('avg', $yaml[0][2]);
        $this->assertEquals('Mark McGwire', $yaml[1][0]);
        $this->assertEquals(65, $yaml[1][1]);
        $this->assertEquals(0.278, $yaml[1][2]);
        $this->assertEquals('Sammy Sosa', $yaml[2][0]);
        $this->assertEquals(63, $yaml[2][1]);
        $this->assertEquals(0.288, $yaml[2][2]);
    }

    public function testParseYaml_withMappingOfMappings()
    {
        $input = <<<'YAML'
Mark McGwire: {hr: 65, avg: 0.278}
Sammy Sosa: {
    hr: 63,
    avg: 0.288,
 }
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(2, $yaml);
        $this->assertArrayHasKey('Mark McGwire', $yaml);
        $this->assertEquals(65, $yaml['Mark McGwire']['hr']);
        $this->assertEquals(0.278, $yaml['Mark McGwire']['avg']);
        $this->assertArrayHasKey('Sammy Sosa', $yaml);
        $this->assertEquals(63, $yaml['Sammy Sosa']['hr']);
        $this->assertEquals(0.288, $yaml['Sammy Sosa']['avg']);
    }

    public function testParseYaml_withMultipleLevels()
    {
        $input = <<<'YAML'
---
key1:
  - item1
  - item2
  - item3
key2:
  - name: example
    value: 42
  - name: test
    value: 100
key3:
  nestedList:
    - subitem
  subKey1: subValue1
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(3, $yaml);
        $this->assertArrayHasKey('key1', $yaml);
        $this->assertInstanceOf(\ArrayObject::class, $yaml['key1']);
        $this->assertCount(3, $yaml['key1']);
        $this->assertEquals('item1', $yaml['key1'][0]);
        $this->assertEquals('item2', $yaml['key1'][1]);
        $this->assertEquals('item3', $yaml['key1'][2]);
        $this->assertArrayHasKey('key2', $yaml);
        $this->assertInstanceOf(\ArrayObject::class, $yaml['key2']);
        $this->assertCount(2, $yaml['key2']);
        $this->assertArrayHasKey('name', $yaml['key2'][0]);
        $this->assertEquals('example', $yaml['key2'][0]['name']);
        $this->assertArrayHasKey('value', $yaml['key2'][0]);
        $this->assertEquals('42', $yaml['key2'][0]['value']);
        $this->assertArrayHasKey('name', $yaml['key2'][1]);
        $this->assertEquals('test', $yaml['key2'][1]['name']);
        $this->assertArrayHasKey('value', $yaml['key2'][1]);
        $this->assertEquals('100', $yaml['key2'][1]['value']);
        $this->assertArrayHasKey('key3', $yaml);
        $this->assertInstanceOf(\ArrayObject::class, $yaml['key3']);
        $this->assertCount(2, $yaml['key3']);
        $this->assertArrayHasKey('nestedList', $yaml['key3']);
        $this->assertInstanceOf(\ArrayObject::class, $yaml['key3']['nestedList']);
        $this->assertCount(1, $yaml['key3']['nestedList']);
        $this->assertEquals('subitem', $yaml['key3']['nestedList'][0]);
        $this->assertArrayHasKey('subKey1', $yaml['key3']);
        $this->assertEquals('subValue1', $yaml['key3']['subKey1']);
    }

    public function testParseYaml_withSectionAfterMultilineString()
    {
        $input = <<<'YAML'
key:
  multilineString: |
    This is a multiline string.
    It has several lines.
    Each line is preserved.
  nestedList:
    - subitem
  subKey1: subValue1
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(1, $yaml);
        $this->assertArrayHasKey('key', $yaml);
        $this->assertInstanceOf(\ArrayObject::class, $yaml['key']);
        $this->assertCount(3, $yaml['key']);
        $this->assertArrayHasKey('multilineString', $yaml['key']);
        $this->assertEquals("This is a multiline string.\nIt has several lines.\nEach line is preserved.\n", $yaml['key']['multilineString']);
        $this->assertArrayHasKey('nestedList', $yaml['key']);
        $this->assertInstanceOf(\ArrayObject::class, $yaml['key']['nestedList']);
        $this->assertCount(1, $yaml['key']['nestedList']);
        $this->assertEquals('subitem', $yaml['key']['nestedList'][0]);
        $this->assertArrayHasKey('subKey1', $yaml['key']);
        $this->assertEquals('subValue1', $yaml['key']['subKey1']);
    }

    public function testParseYaml_withSectionSubItemObject()
    {
        $input = <<<'YAML'
key1:
  multilineString:
    - test2: value2
key2:
  - name: example
    value: 42
  - name: test
    value: 100
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(2, $yaml);
        $this->assertArrayHasKey('key1', $yaml);
        $this->assertInstanceOf(\ArrayObject::class, $yaml['key1']);
        $this->assertArrayHasKey('multilineString', $yaml['key1']);
        $this->assertInstanceOf(\ArrayObject::class, $yaml['key1']['multilineString']);
        $this->assertCount(1, $yaml['key1']['multilineString']);
        $this->assertArrayHasKey('test2', $yaml['key1']['multilineString'][0]);
        $this->assertEquals('value2', $yaml['key1']['multilineString'][0]['test2']);
        $this->assertArrayHasKey('key2', $yaml);
        $this->assertInstanceOf(\ArrayObject::class, $yaml['key2']);
        $this->assertCount(2, $yaml['key2']);
        $this->assertArrayHasKey('name', $yaml['key2'][0]);
        $this->assertEquals('example', $yaml['key2'][0]['name']);
        $this->assertArrayHasKey('value', $yaml['key2'][0]);
        $this->assertEquals('42', $yaml['key2'][0]['value']);
        $this->assertArrayHasKey('name', $yaml['key2'][1]);
        $this->assertEquals('test', $yaml['key2'][1]['name']);
        $this->assertArrayHasKey('name', $yaml['key2'][1]);
        $this->assertEquals('100', $yaml['key2'][1]['value']);
    }

    public function testParseYaml_withInlineMapping()
    {
        $input = <<<'YAML'
key1:
  multilineString:
    - test2: value2
key2: {name: example, value: 42}
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(2, $yaml);
        $this->assertArrayHasKey('key1', $yaml);
        $this->assertInstanceOf(\ArrayObject::class, $yaml['key1']);
        $this->assertArrayHasKey('multilineString', $yaml['key1']);
        $this->assertInstanceOf(\ArrayObject::class, $yaml['key1']['multilineString']);
        $this->assertCount(1, $yaml['key1']['multilineString']);
        $this->assertArrayHasKey('test2', $yaml['key1']['multilineString'][0]);
        $this->assertEquals('value2', $yaml['key1']['multilineString'][0]['test2']);
        $this->assertArrayHasKey('key2', $yaml);
        $this->assertInstanceOf(\ArrayObject::class, $yaml['key2']);
        $this->assertCount(2, $yaml['key2']);
        $this->assertArrayHasKey('name', $yaml['key2']);
        $this->assertEquals('example', $yaml['key2']['name']);
        $this->assertArrayHasKey('value', $yaml['key2']);
        $this->assertEquals('42', $yaml['key2']['value']);
    }

    public function testParseYaml_withMultipleSubKeys()
    {
        $input = <<<'YAML'
key1:
  key11:
    key111: value111
    key112:
      - value112a
      - value112bKey: value112bVal "test"
  key12:
    key121: my-value121
    key122:
      - value122a
      - value122bKey: value122bVal "test"
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(1, $yaml);
        $this->assertArrayHasKey('key1', $yaml);
        $this->assertInstanceOf(\ArrayObject::class, $yaml['key1']);
        $this->assertArrayHasKey('key11', $yaml['key1']);
        $this->assertInstanceOf(\ArrayObject::class, $yaml['key1']['key11']);
        $this->assertArrayHasKey('key111', $yaml['key1']['key11']);
        $this->assertEquals('value111', $yaml['key1']['key11']['key111']);
        $this->assertArrayHasKey('key112', $yaml['key1']['key11']);
        $this->assertInstanceOf(\ArrayObject::class, $yaml['key1']['key11']['key112']);
        $this->assertCount(2, $yaml['key1']['key11']['key112']);
        $this->assertEquals('value112a', $yaml['key1']['key11']['key112'][0]);
        $this->assertArrayHasKey('key12', $yaml['key1']);
        $this->assertInstanceOf(\ArrayObject::class, $yaml['key1']['key12']);
        $this->assertArrayHasKey('key121', $yaml['key1']['key12']);
        $this->assertEquals('my-value121', $yaml['key1']['key12']['key121']);
        $this->assertArrayHasKey('key122', $yaml['key1']['key12']);
        $this->assertInstanceOf(\ArrayObject::class, $yaml['key1']['key12']['key122']);
        $this->assertCount(2, $yaml['key1']['key12']['key122']);
        $this->assertEquals('value122a', $yaml['key1']['key12']['key122'][0]);
    }

    public function testParseYaml_withMappingBetweenSequences()
    {
        $input = <<<'YAML'
? - Detroit Tigers
  - Chicago cubs
: - 2001-07-23

? [ New York Yankees,
    Atlanta Braves ]
: [ 2001-07-02, 2001-08-12,
    2001-08-14 ]
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(2, $yaml);
        $this->assertArrayHasKey('["Detroit Tigers","Chicago cubs"]', $yaml);
        $this->assertCount(1, $yaml['["Detroit Tigers","Chicago cubs"]']);
        $this->assertEquals('2001-07-23', $yaml['["Detroit Tigers","Chicago cubs"]'][0]);
        $this->assertArrayHasKey('["New York Yankees","Atlanta Braves"]', $yaml);
        $this->assertCount(3, $yaml['["New York Yankees","Atlanta Braves"]']);
        $this->assertEquals('2001-07-02', $yaml['["New York Yankees","Atlanta Braves"]'][0]);
        $this->assertEquals('2001-08-12', $yaml['["New York Yankees","Atlanta Braves"]'][1]);
        $this->assertEquals('2001-08-14', $yaml['["New York Yankees","Atlanta Braves"]'][2]);
    }

    public function testParseYaml_withCompactNestedMapping()
    {
        $input = <<<'YAML'
---
# Products purchased
- item    : Super Hoop
  quantity: 1
- item    : Basketball
  quantity: 4
- item    : Big Shoes
  quantity: 1
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertcount(3, $yaml);
        $this->assertArrayHasKey('item', $yaml[0]);
        $this->assertEquals('Super Hoop', $yaml[0]['item']);
        $this->assertArrayHasKey('quantity', $yaml[0]);
        $this->assertEquals(1, $yaml[0]['quantity']);
        $this->assertArrayHasKey('item', $yaml[1]);
        $this->assertEquals('Basketball', $yaml[1]['item']);
        $this->assertArrayHasKey('quantity', $yaml[1]);
        $this->assertEquals(4, $yaml[1]['quantity']);
        $this->assertArrayHasKey('item', $yaml[2]);
        $this->assertEquals('Big Shoes', $yaml[2]['item']);
        $this->assertArrayHasKey('quantity', $yaml[2]);
        $this->assertEquals(1, $yaml[2]['quantity']);
    }

    public function testParseYaml_withBlockStructureIndicators()
    {
        $input = <<<'YAML'
sequence:
- one
- two
mapping:
  ? sky
  : blue
  sea : green
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertcount(2, $yaml);
        $this->assertArrayHasKey('sequence', $yaml);
        $this->assertInstanceOf(\ArrayObject::class, $yaml['sequence']);
        $this->assertCount(2, $yaml['sequence']);
        $this->assertEquals('one', $yaml['sequence'][0]);
        $this->assertEquals('two', $yaml['sequence'][1]);
        $this->assertArrayHasKey('mapping', $yaml);
        $this->assertInstanceOf(\ArrayObject::class, $yaml['mapping']);
        $this->assertCount(2, $yaml['mapping']);
        $this->assertArrayHasKey('sky', $yaml['mapping']);
        $this->assertEquals('blue', $yaml['mapping']['sky']);
        $this->assertArrayHasKey('sea', $yaml['mapping']);
        $this->assertEquals('green', $yaml['mapping']['sea']);
    }

    public function testParseYaml_withExplicitScalarMultipleValues()
    {
        $input = <<<'YAML'
? sky
:
  color: blue
  brightness: high
  temperature: warm
sea: green
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertcount(2, $yaml);
        $this->assertArrayHasKey('sky', $yaml);
        $this->assertInstanceOf(\ArrayObject::class, $yaml['sky']);
        $this->assertCount(3, $yaml['sky']);
        $this->assertArrayHasKey('color', $yaml['sky']);
        $this->assertEquals('blue', $yaml['sky']['color']);
        $this->assertArrayHasKey('brightness', $yaml['sky']);
        $this->assertEquals('high', $yaml['sky']['brightness']);
        $this->assertArrayHasKey('temperature', $yaml['sky']);
        $this->assertEquals('warm', $yaml['sky']['temperature']);
        $this->assertArrayHasKey('sea', $yaml);
        $this->assertEquals('green', $yaml['sea']);
    }

    public function testParseYaml_withFlowCollectionIndicators()
    {
        $input = <<<'YAML'
sequence: [ one, two, ]
mapping: { sky: blue, sea: green }
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertcount(2, $yaml);
        $this->assertArrayHasKey('sequence', $yaml);
        $this->assertInstanceOf(\ArrayObject::class, $yaml['sequence']);
        $this->assertCount(2, $yaml['sequence']);
        $this->assertEquals('one', $yaml['sequence'][0]);
        $this->assertEquals('two', $yaml['sequence'][1]);
        $this->assertArrayHasKey('mapping', $yaml);
        $this->assertInstanceOf(\ArrayObject::class, $yaml['mapping']);
        $this->assertCount(2, $yaml['mapping']);
        $this->assertArrayHasKey('sky', $yaml['mapping']);
        $this->assertEquals('blue', $yaml['mapping']['sky']);
        $this->assertArrayHasKey('sea', $yaml['mapping']);
        $this->assertEquals('green', $yaml['mapping']['sea']);
    }

    public function testParseYaml_withEscapedCharacters()
    {
        $input = <<<'YAML'
- "Fun with \\"
- "\" \a \b \e \f"
- "\n \r \t \v \0"
- "\  \_ \N \L \P \x41 \u0041 \U00000041"
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->compareUnicodeStrings('Fun with \\', $yaml[0]);
        $this->compareUnicodeStrings("\" \x07 \x08 \x1b \x0C", $yaml[1]);
        $this->compareUnicodeStrings("\x0A \x0D \x09 \x0B \x00", $yaml[2]);
        $this->compareUnicodeStrings("\x20 \xC2\xA0 \xC2\x85 \u{2028} \u{2029} \x41 \x41 \x41", $yaml[3]);
    }

    public function testParseYaml_withInvalidEscapedChars_c()
    {
        $input = <<<'YAML'
- "\c"
YAML;

        $this->expectException(LexerException::class);
        $this->expectExceptionMessage('Invalid escape sequence \'\c\' in line 1, column 3');
        $this->yamlParser->parse($input);
    }

    public function testParseYaml_withInvalidUnicode_qDash()
    {
        $input = <<<'YAML'
- "\xq-"
YAML;

        $this->expectException(LexerException::class);
        $this->expectExceptionMessage('Invalid escape sequence \'\x\' in line 1, column 5');
        $this->yamlParser->parse($input);
    }

    public function testParseYaml_withIndentationSpaces()
    {
        $input = <<<'YAML'
  # Leading comment line spaces are
   # neither content nor indentation.
    
Not indented:
 By one space: |
    By four
      spaces
 Flow style: [    # Leading spaces
   By two,        # in flow style
  Also by two,    # are neither
    ]             # indentation.
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(1, $yaml);
        $this->assertArrayHasKey('Not indented', $yaml);
        $this->assertInstanceOf(\ArrayObject::class, $yaml['Not indented']);
        $this->assertArrayHasKey('By one space', $yaml['Not indented']);
        $this->assertEquals("By four\n  spaces\n", $yaml['Not indented']['By one space']);
        $this->assertArrayHasKey('Flow style', $yaml['Not indented']);
        $this->assertInstanceOf(\ArrayObject::class, $yaml['Not indented']['Flow style']);
        $this->assertCount(2, $yaml['Not indented']['Flow style']);
        $this->compareUnicodeStrings('By two', $yaml['Not indented']['Flow style'][0]);
        $this->compareUnicodeStrings('Also by two', $yaml['Not indented']['Flow style'][1]);
    }

    public function testParseYaml_withIndentationIndicators()
    {
        $input = <<<'YAML'
? a
: - b
  -  - c
     - d
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(1, $yaml);
        $this->assertArrayHasKey('a', $yaml);
        $this->assertInstanceOf(\ArrayObject::class, $yaml['a']);
        $this->assertCount(2, $yaml['a']);
        $this->assertEquals('b', $yaml['a'][0]);
        $this->assertInstanceOf(\ArrayObject::class, $yaml['a'][1]);
        $this->assertCount(2, $yaml['a'][1]);
        $this->assertEquals('c', $yaml['a'][1][0]);
        $this->assertEquals('d', $yaml['a'][1][1]);
    }

    public function testParseYaml_withSeparationSpaces()
    {
        $input = <<<'YAML'
- foo: bar
- - baz
  - baz
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(2, $yaml);
        $this->assertArrayHasKey('foo', $yaml[0]);
        $this->assertEquals('bar', $yaml[0]['foo']);
        $this->assertInstanceOf(\ArrayObject::class, $yaml[1]);
        $this->assertCount(2, $yaml[1]);
        $this->assertEquals('baz', $yaml[1][0]);
        $this->assertEquals('baz', $yaml[1][1]);
    }

    public function testParseYaml_withSeparationSpacesInStructuredKey()
    {
        $input = <<<'YAML'
{ first: Sammy, last: Sosa }:
# Statistics:
  hr:  # Home runs
     65
  avg: # Average
   0.278
key: value
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(2, $yaml);
        $this->assertArrayHasKey('{"first":"Sammy","last":"Sosa"}', $yaml);
        $this->assertInstanceOf(\ArrayObject::class, $yaml['{"first":"Sammy","last":"Sosa"}']);
        $this->assertCount(2, $yaml['{"first":"Sammy","last":"Sosa"}']);
        $this->assertArrayHasKey('hr', $yaml['{"first":"Sammy","last":"Sosa"}']);
        $this->assertEquals(65, $yaml['{"first":"Sammy","last":"Sosa"}']['hr']);
        $this->assertArrayHasKey('avg', $yaml['{"first":"Sammy","last":"Sosa"}']);
        $this->assertEquals(0.278, $yaml['{"first":"Sammy","last":"Sosa"}']['avg']);
        $this->assertArrayHasKey('key', $yaml);
        $this->assertEquals('value', $yaml['key']);
    }

    public function testParseYaml_withDoubleQuotedImplicitKeys()
    {
        $input = <<<'YAML'
"implicit block key" : [
  "implicit flow key" : value,
 ]
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(1, $yaml);
        $this->assertArrayHasKey('implicit block key', $yaml);
        $this->assertInstanceOf(\ArrayObject::class, $yaml['implicit block key']);
        $this->assertCount(1, $yaml['implicit block key']);
        $this->assertArrayHasKey(0, $yaml['implicit block key']);
        $this->assertArrayHasKey('implicit flow key', $yaml['implicit block key'][0]);
        $this->assertEquals('value', $yaml['implicit block key'][0]['implicit flow key']);
    }

    public function testParseYaml_withSingleQuotedImplicitKeys()
    {
        $input = <<<'YAML'
'implicit block key' : [
  'implicit flow key' : value,
 ]
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(1, $yaml);
        $this->assertArrayHasKey('implicit block key', $yaml);
        $this->assertInstanceOf(\ArrayObject::class, $yaml['implicit block key']);
        $this->assertCount(1, $yaml['implicit block key']);
        $this->assertArrayHasKey(0, $yaml['implicit block key']);
        $this->assertArrayHasKey('implicit flow key', $yaml['implicit block key'][0]);
        $this->assertEquals('value', $yaml['implicit block key'][0]['implicit flow key']);
    }

    public function testParseYaml_withPlainImplicitKeys()
    {
        $input = <<<'YAML'
implicit block key : [
  implicit flow key : value,
 ]
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(1, $yaml);
        $this->assertArrayHasKey('implicit block key', $yaml);
        $this->assertInstanceOf(\ArrayObject::class, $yaml['implicit block key']);
        $this->assertCount(1, $yaml['implicit block key']);
        $this->assertArrayHasKey(0, $yaml['implicit block key']);
        $this->assertArrayHasKey('implicit flow key', $yaml['implicit block key'][0]);
        $this->assertEquals('value', $yaml['implicit block key'][0]['implicit flow key']);
    }

    public function testParseYaml_withPlainICharacters()
    {
        $input = <<<'YAML'
# Outside flow collection:
- ::vector
- ": - ()"
- Up, up, and away!
- -123
- https://example.com/foo#bar
# Inside flow collection:
- [ ::vector,
  ": - ()",
  "Up, up and away!",
  -123,
  https://example.com/foo#bar ]
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(6, $yaml);
        $this->compareUnicodeStrings('::vector', $yaml[0]);
        $this->compareUnicodeStrings(': - ()', $yaml[1]);
        $this->compareUnicodeStrings('Up, up, and away!', $yaml[2]);
        $this->compareUnicodeStrings('-123', $yaml[3]);
        $this->compareUnicodeStrings('https://example.com/foo#bar', $yaml[4]);
        $this->assertInstanceOf(\ArrayObject::class, $yaml[5]);
        $this->assertCount(5, $yaml[5]);
        $this->compareUnicodeStrings('::vector', $yaml[5][0]);
        $this->compareUnicodeStrings(': - ()', $yaml[5][1]);
        $this->compareUnicodeStrings('Up, up and away!', $yaml[5][2]);
        $this->compareUnicodeStrings('-123', $yaml[5][3]);
        $this->compareUnicodeStrings('https://example.com/foo#bar', $yaml[5][4]);
    }

    public function testParseYaml_withFlowSequence()
    {
        $input = <<<'YAML'
- [ one, two, ]
- [three ,four]
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(2, $yaml);
        $this->assertInstanceOf(\ArrayObject::class, $yaml[0]);
        $this->assertCount(2, $yaml[0]);
        $this->compareUnicodeStrings('one', $yaml[0][0]);
        $this->compareUnicodeStrings('two', $yaml[0][1]);
        $this->assertInstanceOf(\ArrayObject::class, $yaml[1]);
        $this->assertCount(2, $yaml[1]);
        $this->compareUnicodeStrings('three', $yaml[1][0]);
        $this->compareUnicodeStrings('four', $yaml[1][1]);
    }

    public function testParseYaml_withFlowSequenceEntries()
    {
        $input = <<<'YAML'
[
"double
 quoted", 'single
           quoted',
plain
 text, [ nested ],
single: pair,
]
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(5, $yaml);
        $this->assertEquals('double quoted', $yaml[0]);
        $this->assertEquals('single quoted', $yaml[1]);
        $this->assertEquals('plain text', $yaml[2]);
        $this->assertInstanceOf(\ArrayObject::class, $yaml[3]);
        $this->assertCount(1, $yaml[3]);
        $this->assertEquals('nested', $yaml[3][0]);
        $this->assertCount(1, $yaml[4]);
        $this->assertArrayHasKey('single', $yaml[4]);
        $this->assertEquals('pair', $yaml[4]['single']);
    }

    public function testParseYaml_withFlowMappings()
    {
        $input = <<<'YAML'
- { one : two , three: four , }
- {five: six,seven : eight}
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(2, $yaml);
        $this->assertArrayHasKey('one', $yaml[0]);
        $this->assertEquals('two', $yaml[0]['one']);
        $this->assertArrayHasKey('three', $yaml[0]);
        $this->assertEquals('four', $yaml[0]['three']);
        $this->assertArrayHasKey('five', $yaml[1]);
        $this->assertEquals('six', $yaml[1]['five']);
        $this->assertArrayHasKey('seven', $yaml[1]);
        $this->assertEquals('eight', $yaml[1]['seven']);
    }

    public function testParseYaml_withFlowMappingEntries()
    {
        $input = <<<'YAML'
{
? explicit: entry,
implicit: entry,
?
}
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(3, $yaml);
        $this->assertArrayHasKey('explicit', $yaml);
        $this->assertEquals('entry', $yaml['explicit']);
        $this->assertArrayHasKey('implicit', $yaml);
        $this->assertEquals('entry', $yaml['implicit']);
        $this->assertArrayHasKey('null', $yaml);
        $this->assertNull($yaml['null']);
    }

    public function testParseYaml_withFlowMappingSeparateValues()
    {
        $input = <<<'YAML'
{
unquoted : "separate",
https://foo.com,
omitted value:,
: omitted key,
}
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(4, $yaml);
        $this->assertArrayHasKey('unquoted', $yaml);
        $this->assertEquals('separate', $yaml['unquoted']);
        $this->assertArrayHasKey('https://foo.com', $yaml);
        $this->assertNull($yaml['https://foo.com']);
        $this->assertArrayHasKey('omitted value', $yaml);
        $this->assertNull($yaml['omitted value']);
        $this->assertArrayHasKey('null', $yaml);
        $this->assertEquals('omitted key', $yaml['null']);
    }

    public function testParseYaml_withFlowMappingAdjacentValues()
    {
        $input = <<<'YAML'
{
"adjacent":value,
"readable": value,
"empty":
}
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(3, $yaml);
        $this->assertArrayHasKey('adjacent', $yaml);
        $this->assertEquals('value', $yaml['adjacent']);
        $this->assertArrayHasKey('readable', $yaml);
        $this->assertEquals('value', $yaml['readable']);
        $this->assertArrayHasKey('empty', $yaml);
        $this->assertNull($yaml['empty']);
    }

    public function testParseYaml_withSinglePairFlowMappings()
    {
        $input = <<<'YAML'
[
foo: bar
]
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(1, $yaml);
        $this->assertArrayHasKey('foo', $yaml[0]);
        $this->assertEquals('bar', $yaml[0]['foo']);
    }

    public function testParseYaml_withSinglePairExplicitEntry()
    {
        $input = <<<'YAML'
[
? foo
 bar : baz
]
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(1, $yaml);
        $this->assertArrayHasKey('foo bar', $yaml[0]);
        $this->assertEquals('baz', $yaml[0]['foo bar']);
    }

    public function testParseYaml_withSinglePairImplicitEntries()
    {
        $input = <<<'YAML'
- [ YAML : separate ]
- [ : empty key entry ]
- [ {JSON: like}:adjacent ]
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(3, $yaml);
        $this->assertArrayHasKey('YAML', $yaml[0][0]);
        $this->assertEquals('separate', $yaml[0][0]['YAML']);
        $this->assertArrayHasKey('null', $yaml[1][0]);
        $this->assertEquals('empty key entry', $yaml[1][0]['null']);
        $this->assertArrayHasKey('{"JSON":"like"}', $yaml[2][0]);
        $this->assertEquals('adjacent', $yaml[2][0]['{"JSON":"like"}']);
    }

    public function testParseYaml_withFlowContent()
    {
        $input = <<<'YAML'
- [ a, b ]
- { a: b }
- "a"
- 'b'
- c
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(5, $yaml);
        $this->assertInstanceOf(\ArrayObject::class, $yaml[0]);
        $this->assertCount(2, $yaml[0]);
        $this->assertEquals('a', $yaml[0][0]);
        $this->assertEquals('b', $yaml[0][1]);
        $this->assertInstanceOf(\ArrayObject::class, $yaml[1]);
        $this->assertCount(1, $yaml[1]);
        $this->assertArrayHasKey('a', $yaml[1]);
        $this->assertEquals('b', $yaml[1]['a']);
        $this->assertEquals('a', $yaml[2]);
        $this->assertEquals('b', $yaml[3]);
        $this->assertEquals('c', $yaml[4]);
    }

    public function testParseYaml_withInvalidImplicitKey_withLinebreak()
    {
        $input = <<<'YAML'
[ foo
 bar: invalid]
YAML;

        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Expected \',\' or \']\' in flow sequence at line 2, column 1');
        $this->yamlParser->parse($input);
    }

    public function testParseYaml_withInvalidImplicitKey_withTooLongKey()
    {
        $input = <<<'YAML'
[ <long>: invalid]
YAML;
        $input = str_replace('<long>', str_repeat('a', 1001), $input);

        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Mapping keys cannot be longer than 1000 characters at line 1, column 1001');
        $this->yamlParser->parse($input);
    }

    public function testParseYaml_withSequenceFlowKey()
    {
        $input = <<<'YAML'
[a, [b, c]]: value
[[a, b], c]: value2
[[a,b], [c, d]]: value3
YAML;

        $yaml = $this->yamlParser->parse($input);
        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(3, $yaml);
        $this->assertArrayHasKey('["a",["b","c"]]', $yaml);
        $this->assertEquals('value', $yaml['["a",["b","c"]]']);
        $this->assertArrayHasKey('[["a","b"],"c"]', $yaml);
        $this->assertEquals('value2', $yaml['[["a","b"],"c"]']);
        $this->assertArrayHasKey('[["a","b"],["c","d"]]', $yaml);
        $this->assertEquals('value3', $yaml['[["a","b"],["c","d"]]']);
    }

    public function testParseYaml_withMappingFlowKey()
    {
        $input = <<<'YAML'
{a: b, c: d}: value1
{e: f, g: {h: i}}: value2
{{j: k, l: m}: n, o: p}: value3
YAML;

        $yaml = $this->yamlParser->parse($input);
        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(3, $yaml);
        $this->assertArrayHasKey('{"a":"b","c":"d"}', $yaml);
        $this->assertEquals('value1', $yaml['{"a":"b","c":"d"}']);
        $this->assertArrayHasKey('{"e":"f","g":{"h":"i"}}', $yaml);
        $this->assertEquals('value2', $yaml['{"e":"f","g":{"h":"i"}}']);
        $this->assertArrayHasKey('{"{\"j\":\"k\",\"l\":\"m\"}":"n","o":"p"}', $yaml);
        $this->assertEquals('value3', $yaml['{"{\"j\":\"k\",\"l\":\"m\"}":"n","o":"p"}']);
    }

    public function testParseYaml_withBlockSequence()
    {
        $input = <<<'YAML'
block sequence:
  - one
  - two : three
YAML;

        $yaml = $this->yamlParser->parse($input);
        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(1, $yaml);
        $this->assertArrayHasKey('block sequence', $yaml);
        $this->assertInstanceOf(\ArrayObject::class, $yaml['block sequence']);
        $this->assertCount(2, $yaml['block sequence']);
        $this->assertEquals('one', $yaml['block sequence'][0]);
        $this->assertArrayHasKey('two', $yaml['block sequence'][1]);
        $this->assertEquals('three', $yaml['block sequence'][1]['two']);
    }

    public function testParseYaml_withBlockSequenceEntryTypes()
    {
        $input = <<<'YAML'
- # Empty
- |
 block node
- - one # Compact
  - two # sequence
- one: two # Compact mapping
YAML;

        $yaml = $this->yamlParser->parse($input);
        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(4, $yaml);
        $this->assertEquals('', $yaml[0]);
        $this->assertEquals("block node\n", $yaml[1]);
        $this->assertInstanceOf(\ArrayObject::class, $yaml[2]);
        $this->assertCount(2, $yaml[2]);
        $this->assertEquals('one', $yaml[2][0]);
        $this->assertEquals('two', $yaml[2][1]);
        $this->assertArrayHasKey('one', $yaml[3]);
        $this->assertEquals('two', $yaml[3]['one']);
    }

    public function testParseYaml_withFlowNodes()
    {
        $input = <<<'YAML'
- !!str "a"
- 'b'
- &anchor "c"
- *anchor
- !!str
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(5, $yaml);
        $this->assertEquals('a', $yaml[0]);
        $this->assertEquals('b', $yaml[1]);
        $this->assertEquals('c', $yaml[2]);
        $this->assertEquals('c', $yaml[3]);
        $this->assertEquals('', $yaml[4]);
    }

    public function testParseYaml_withImplicitBlockMappingEntries()
    {
        $this->markTestSkipped('Empty key and value are not yet supported in block mappings.');
        $input = <<<'YAML'
plain key: in-line value
: # Both empty
"quoted key":
- entry
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(3, $yaml);
        $this->assertArrayHasKey('plain key', $yaml);
        $this->assertEquals('in-line value', $yaml['plain key']);
        $this->assertArrayHasKey('null', $yaml);
        $this->assertNull($yaml['null']);
        $this->assertArrayHasKey('quoted key', $yaml);
        $this->assertInstanceOf(\ArrayObject::class, $yaml['quoted key']);
        $this->assertCount(1, $yaml['quoted key']);
        $this->assertEquals('entry', $yaml['quoted key'][0]);
    }

    public function testParseYaml_withBlockNodeTypes()
    {
        $input = <<<'YAML'
-
  "flow in block"
- >
 Block scalar
- !!map # Block collection
  foo : bar
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(3, $yaml);
        $this->assertEquals('flow in block', $yaml[0]);
        $this->assertEquals("Block scalar\n", $yaml[1]);
        $this->assertEquals('bar', $yaml[2]['foo']);
    }

    public function testParseYaml_withBlockCollectionNodes()
    {
        $this->markTestSkipped('Sub sequence after tags is not yet supported correctly.');
        $input = <<<'YAML'
sequence: !!seq
- entry
- !!seq
 - nested
mapping: !!map
 foo: bar
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(2, $yaml);
        $this->assertArrayHasKey('sequence', $yaml);
        $this->assertInstanceOf(\ArrayObject::class, $yaml['sequence']);
        $this->assertCount(2, $yaml['sequence']);
        $this->assertEquals('entry', $yaml['sequence'][0]);
        $this->assertInstanceOf(\ArrayObject::class, $yaml['sequence'][1]);
        $this->assertCount(1, $yaml['sequence'][1]);
        $this->assertEquals('nested', $yaml['sequence'][1][0]);
        $this->assertArrayHasKey('mapping', $yaml);
        $this->assertInstanceOf(\ArrayObject::class, $yaml['mapping']);
        $this->assertCount(1, $yaml['mapping']);
        $this->assertArrayHasKey('foo', $yaml['mapping']);
        $this->assertEquals('bar', $yaml['mapping']['foo']);
    }

    private function compareUnicodeStrings(string $expected, string $actual): void
    {
        $expectedChars = [];
        $actualChars = [];

        for ($i = 0; $i < mb_strlen($expected, 'UTF-8'); $i++) {
            $char = mb_substr($expected, $i, 1, 'UTF-8');
            $code = mb_ord($char, 'UTF-8');
            $expectedChars[] = $code;
        }
        for ($i = 0; $i < mb_strlen($actual, 'UTF-8'); $i++) {
            $char = mb_substr($actual, $i, 1, 'UTF-8');
            $code = mb_ord($char, 'UTF-8');
            $actualChars[] = $code;
        }

        $this->assertCount(count($expectedChars), $actualChars, 'The number of Unicode characters does not match.');

        foreach ($expectedChars as $index => $char) {
            $this->assertEquals($char, $actualChars[$index], "Character at position {$index} does not match.");
        }
    }
}
