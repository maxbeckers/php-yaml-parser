<?php

namespace MaxBeckers\YamlParser\Tests;

use MaxBeckers\YamlParser\Exception\LexerException;
use MaxBeckers\YamlParser\YamlParser;
use PHPUnit\Framework\TestCase;

class YamlParserTagsTest extends TestCase
{
    private YamlParser $yamlParser;

    protected function setUp(): void
    {
        $this->yamlParser = new YamlParser();
    }

    public function testParseYaml_withExplicitTags()
    {
        $input = <<<'YAML'
---
not-date: !!str 2002-04-28

picture: !!binary |
 R0lGODlhDAAMAIQAAP//9/X
 17unp5WZmZgAAAOfn515eXv
 Pz7Y6OjuDg4J+fn5OTk6enp
 56enmleECcgggoBADs=

application specific tag: !something |
 The semantics of the tag
 above may be different for
 different documents.
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(3, $yaml);
        $this->assertArrayHasKey('not-date', $yaml);
        $this->assertEquals('2002-04-28', $yaml['not-date']);
        $this->assertArrayHasKey('picture', $yaml);
        $this->assertArrayHasKey('application specific tag', $yaml);
        $this->assertEquals("The semantics of the tag\nabove may be different for\ndifferent documents.\n", $yaml['application specific tag']);
    }

    public function testParseYaml_withOnlyTagData()
    {
        $input = <<<'YAML'
!!str "foo"
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertEquals('foo', $yaml);
    }

    public function testParseYaml_withExplicitTagsWIthDirective()
    {
        $input = <<<'YAML'
%TAG !yaml! tag:yaml.org,2002:
---
!yaml!str "foo"
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertEquals('foo', $yaml);
    }

    public function testParseYaml_withGlobalTags()
    {
        $input = <<<'YAML'
%TAG ! tag:clarkevans.com,2002:
--- !shape
  # Use the ! handle for presenting
  # tag:clarkevans.com,2002:circle
- !circle
  center: &ORIGIN {x: 73, y: 129}
  radius: 7
- !line
  start: *ORIGIN
  finish: { x: 89, y: 102 }
- !label
  start: *ORIGIN
  color: 0xFFEEBB
  text: Pretty vector drawing.
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(3, $yaml);
        $this->assertArrayHasKey('center', $yaml[0]);
        $this->assertInstanceOf(\ArrayObject::class, $yaml[0]['center']);
        $this->assertArrayHasKey('x', $yaml[0]['center']);
        $this->assertEquals(73, $yaml[0]['center']['x']);
        $this->assertArrayHasKey('y', $yaml[0]['center']);
        $this->assertEquals(129, $yaml[0]['center']['y']);
        $this->assertArrayHasKey('radius', $yaml[0]);
        $this->assertEquals(7, $yaml[0]['radius']);
        $this->assertArrayHasKey('start', $yaml[1]);
        $this->assertInstanceOf(\ArrayObject::class, $yaml[1]['start']);
        $this->assertArrayHasKey('x', $yaml[1]['start']);
        $this->assertEquals(73, $yaml[1]['start']['x']);
        $this->assertArrayHasKey('y', $yaml[1]['start']);
        $this->assertEquals(129, $yaml[1]['start']['y']);
        $this->assertArrayHasKey('finish', $yaml[1]);
        $this->assertInstanceOf(\ArrayObject::class, $yaml[1]['finish']);
        $this->assertArrayHasKey('x', $yaml[1]['finish']);
        $this->assertEquals(89, $yaml[1]['finish']['x']);
        $this->assertArrayHasKey('y', $yaml[1]['finish']);
        $this->assertEquals(102, $yaml[1]['finish']['y']);
        $this->assertArrayHasKey('text', $yaml[2]);
        $this->assertEquals('Pretty vector drawing.', $yaml[2]['text']);
    }

    public function testParseYaml_withRepeatedTagDirective_expectException()
    {
        $input = <<<'YAML'
%TAG ! !foo
%TAG ! !foo
bar
YAML;

        $this->expectException(LexerException::class);
        $this->expectExceptionMessage('TAG directive with handle \'!\' already defined earlier in line 2, column 0');
        $this->yamlParser->parse($input);
    }

    public function testParseYaml_withPrivateAndGlobalTags()
    {
        $input = <<<'YAML'
# Private
!foo "bar"
...
# Global
%TAG ! tag:example.com,2000:app/
---
!foo "bar"
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(2, $yaml);
        $this->assertEquals('bar', $yaml[0]);
        $this->assertEquals('bar', $yaml[1]);
    }

    public function testParseYaml_withIntAsInterval()
    {
        $input = <<<'YAML'
%TAG !! tag:example.com,2000:app/
---
!!int 1 - 3 # Interval, not integer
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertEquals('1 - 3', $yaml);
    }

    public function testParseYaml_withTagHandle()
    {
        $input = <<<'YAML'
%TAG !e! tag:example.com,2000:app/
---
!e!foo "bar"
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertEquals('bar', $yaml);
    }

    public function testParseYaml_withTagHandleDefinedPerDocument()
    {
        $input = <<<'YAML'
%TAG !m! !my-
--- # Bulb here
!m!light fluorescent
...
%TAG !m! !my-
--- # Color here
!m!light green
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(2, $yaml);
        $this->assertEquals('fluorescent', $yaml[0]);
        $this->assertEquals('green', $yaml[1]);
    }

    public function testParseYaml_withMappingKeyTags()
    {
        $input = <<<'YAML'
!!str "foo": !!int "42"
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(1, $yaml);
        $this->assertArrayHasKey('foo', $yaml);
        $this->assertEquals(42, $yaml['foo']);
    }

    public function testParseYaml_withMappingKeyTagsAndComplexValueTag()
    {
        $input = <<<'YAML'
!<tag:yaml.org,2002:str> foo :
  !<!bar> baz
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(1, $yaml);
        $this->assertArrayHasKey('foo', $yaml);
        $this->assertEquals('baz', $yaml['foo']);
    }

    public function testParseYaml_withInvalidVerbatimTag()
    {
        $input = <<<'YAML'
- !<!> foo
YAML;

        $this->expectException(LexerException::class);
        $this->expectExceptionMessage('Invalid verbatim tag format \'!<!>\' in line 1, column 2');
        $this->yamlParser->parse($input);
    }

    public function testParseYaml_withInvalidTag()
    {
        $input = <<<'YAML'
- !<$:?> bar
YAML;

        $this->expectException(LexerException::class);
        $this->expectExceptionMessage('Invalid verbatim tag format \'!<$:?>\' in line 1, column 2');
        $this->yamlParser->parse($input);
    }

    public function testParseYaml_withTagShorthands()
    {
        $input = <<<'YAML'
%TAG !e! tag:example.com,2000:app/
---
- !local foo
- !!str bar
- !e!tag%21 baz
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(3, $yaml);
        $this->assertEquals('foo', $yaml[0]);
        $this->assertEquals('bar', $yaml[1]);
        $this->assertEquals('baz', $yaml[2]);
    }

    public function testParseYaml_withInvalidTagShorthandEmpty()
    {
        $input = <<<'YAML'
%TAG !e! tag:example,2000:app/
---
- !e! foo
YAML;

        $this->expectException(LexerException::class);
        $this->expectExceptionMessage('Invalid tag format \'!e!\' in line 3, column 2');
        $this->yamlParser->parse($input);
    }

    public function testParseYaml_withInvalidTagShorthandUndefined()
    {
        $input = <<<'YAML'
%TAG !e! tag:example,2000:app/
---
- !h!bar baz
YAML;

        $this->expectException(LexerException::class);
        $this->expectExceptionMessage('Undefined tag handle \'!h!\' in line 3, column 2');
        $this->yamlParser->parse($input);
    }

    public function testParseYaml_withNonSpecificTags()
    {
        $input = <<<'YAML'
# Assuming conventional resolution:
- "12"
- 12
- ! 12
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(3, $yaml);
        $this->assertEquals('12', $yaml[0]);
        $this->assertEquals(12, $yaml[1]);
        $this->assertEquals('12', $yaml[2]);
    }

    public function testParseYaml_withEmptyContent()
    {
        $input = <<<'YAML'
{
  foo : !!str,
  !!str : bar,
}
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(2, $yaml);
        $this->assertArrayHasKey('foo', $yaml);
        $this->assertEquals('', $yaml['foo']);
        $this->assertArrayHasKey('', $yaml);
        $this->assertEquals('bar', $yaml['']);
    }

    public function testParseYaml_withMap()
    {
        $input = <<<'YAML'
Block style: !!map
  Clark : Evans
  Ingy  : döt Net
  Oren  : Ben-Kiki

Flow style: !!map { Clark: Evans, Ingy: döt Net, Oren: Ben-Kiki }
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(2, $yaml);
        $this->assertArrayHasKey('Block style', $yaml);
        $this->assertInstanceOf(\ArrayObject::class, $yaml['Block style']);
        $this->assertCount(3, $yaml['Block style']);
        $this->assertArrayHasKey('Clark', $yaml['Block style']);
        $this->assertEquals('Evans', $yaml['Block style']['Clark']);
        $this->assertArrayHasKey('Ingy', $yaml['Block style']);
        $this->assertEquals('döt Net', $yaml['Block style']['Ingy']);
        $this->assertArrayHasKey('Oren', $yaml['Block style']);
        $this->assertEquals('Ben-Kiki', $yaml['Block style']['Oren']);
        $this->assertArrayHasKey('Flow style', $yaml);
        $this->assertInstanceOf(\ArrayObject::class, $yaml['Flow style']);
        $this->assertCount(3, $yaml['Flow style']);
        $this->assertArrayHasKey('Clark', $yaml['Flow style']);
        $this->assertEquals('Evans', $yaml['Flow style']['Clark']);
        $this->assertArrayHasKey('Ingy', $yaml['Flow style']);
        $this->assertEquals('döt Net', $yaml['Flow style']['Ingy']);
        $this->assertArrayHasKey('Oren', $yaml['Flow style']);
        $this->assertEquals('Ben-Kiki', $yaml['Flow style']['Oren']);
    }

    public function testParseYaml_withSeq()
    {
        $input = <<<'YAML'
Block style: !!seq
- Clark Evans
- Ingy döt Net
- Oren Ben-Kiki

Flow style: !!seq [ Clark Evans, Ingy döt Net, Oren Ben-Kiki ]
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(2, $yaml);
        $this->assertArrayHasKey('Block style', $yaml);
        $this->assertInstanceOf(\ArrayObject::class, $yaml['Block style']);
        $this->assertCount(3, $yaml['Block style']);
        $this->assertEquals('Clark Evans', $yaml['Block style'][0]);
        $this->assertEquals('Ingy döt Net', $yaml['Block style'][1]);
        $this->assertEquals('Oren Ben-Kiki', $yaml['Block style'][2]);
        $this->assertArrayHasKey('Flow style', $yaml);
        $this->assertInstanceOf(\ArrayObject::class, $yaml['Flow style']);
        $this->assertCount(3, $yaml['Flow style']);
        $this->assertEquals('Clark Evans', $yaml['Flow style'][0]);
        $this->assertEquals('Ingy döt Net', $yaml['Flow style'][1]);
        $this->assertEquals('Oren Ben-Kiki', $yaml['Flow style'][2]);
    }

    public function testParseYaml_withStr()
    {
        $input = <<<'YAML'
Block style: !!str |-
  String: just a theory.

Flow style: !!str "String: just a theory."
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(2, $yaml);
        $this->assertArrayHasKey('Block style', $yaml);
        $this->assertEquals('String: just a theory.', $yaml['Block style']);
        $this->assertArrayHasKey('Flow style', $yaml);
        $this->assertEquals('String: just a theory.', $yaml['Flow style']);
    }

    public function testParseYaml_withNull()
    {
        $input = <<<'YAML'
!!null null: value for null key
key with null value: !!null null
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(2, $yaml);
        $this->assertArrayHasKey('null', $yaml);
        $this->assertEquals('value for null key', $yaml['null']);
        $this->assertArrayHasKey('key with null value', $yaml);
        $this->assertNull($yaml['key with null value']);
    }

    public function testParseYaml_withBool()
    {
        $input = <<<'YAML'
This YAML is a superset of JSON: !!bool true
Pluto is a planet: !!bool false
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(2, $yaml);
        $this->assertArrayHasKey('This YAML is a superset of JSON', $yaml);
        $this->assertTrue($yaml['This YAML is a superset of JSON']);
        $this->assertArrayHasKey('Pluto is a planet', $yaml);
        $this->assertFalse($yaml['Pluto is a planet']);
    }

    public function testParseYaml_withInt()
    {
        $input = <<<'YAML'
negative: !!int -12
zero: !!int 0
positive: !!int 34
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(3, $yaml);
        $this->assertArrayHasKey('negative', $yaml);
        $this->assertEquals(-12, $yaml['negative']);
        $this->assertArrayHasKey('zero', $yaml);
        $this->assertEquals(0, $yaml['zero']);
        $this->assertArrayHasKey('positive', $yaml);
        $this->assertEquals(34, $yaml['positive']);
    }

    public function testParseYaml_withFloat()
    {
        $input = <<<'YAML'
negative: !!float -1
zero: !!float 0
positive: !!float 2.3e4
infinity: !!float .inf
not a number: !!float .nan
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(5, $yaml);
        $this->assertArrayHasKey('negative', $yaml);
        $this->assertEquals(-1.0, $yaml['negative']);
        $this->assertArrayHasKey('zero', $yaml);
        $this->assertEquals(0.0, $yaml['zero']);
        $this->assertArrayHasKey('positive', $yaml);
        $this->assertEquals(23000.0, $yaml['positive']);
        $this->assertArrayHasKey('infinity', $yaml);
        $this->assertEquals(INF, $yaml['infinity']);
        $this->assertArrayHasKey('not a number', $yaml);
        $this->assertNan($yaml['not a number']);
    }

    public function testParseYaml_withJSONTagResolution()
    {
        $this->markTestSkipped('Incorrect tag resolution for JSON tags (Null) is handled as null because of strtolower.');
        $input = <<<'YAML'
A null: null
Booleans: [ true, false ]
Integers: [ 0, -0, 3, -19 ]
Floats: [ 0., -0.0, 12e03, -2E+05 ]
Invalid: [ True, Null,
  0o7, 0x3A, +12.3 ]
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(5, $yaml);
        $this->assertArrayHasKey('A null', $yaml);
        $this->assertNull($yaml['A null']);
        $this->assertArrayHasKey('Booleans', $yaml);
        $this->assertInstanceOf(\ArrayObject::class, $yaml['Booleans']);
        $this->assertCount(2, $yaml['Booleans']);
        $this->assertTrue($yaml['Booleans'][0]);
        $this->assertFalse($yaml['Booleans'][1]);
        $this->assertArrayHasKey('Integers', $yaml);
        $this->assertInstanceOf(\ArrayObject::class, $yaml['Integers']);
        $this->assertCount(4, $yaml['Integers']);
        $this->assertEquals(0, $yaml['Integers'][0]);
        $this->assertEquals(0, $yaml['Integers'][1]);
        $this->assertEquals(3, $yaml['Integers'][2]);
        $this->assertEquals(-19, $yaml['Integers'][3]);
        $this->assertArrayHasKey('Floats', $yaml);
        $this->assertInstanceOf(\ArrayObject::class, $yaml['Floats']);
        $this->assertCount(4, $yaml['Floats']);
        $this->assertEquals(0.0, $yaml['Floats'][0]);
        $this->assertEquals(-0.0, $yaml['Floats'][1]);
        $this->assertEquals(12000.0, $yaml['Floats'][2]);
        $this->assertEquals(-200000.0, $yaml['Floats'][3]);
        $this->assertArrayHasKey('Invalid', $yaml);
        $this->assertInstanceOf(\ArrayObject::class, $yaml['Invalid']);
        $this->assertCount(5, $yaml['Invalid']);
        $this->assertEquals('True', $yaml['Invalid'][0]);
        $this->assertEquals('Null', $yaml['Invalid'][1]);
        $this->assertEquals('0o7', $yaml['Invalid'][2]);
        $this->assertEquals('0x3A', $yaml['Invalid'][3]);
        $this->assertEquals('+12.3', $yaml['Invalid'][4]);
    }

    public function testParseYaml_withCoreTagResolution()
    {
        $input = <<<'YAML'
A null: null
Also a null: # Empty
Not a null: ""
Booleans: [ true, True, false, FALSE ]
Integers: [ 0, 0o7, 0x3A, -19 ]
Floats: [
  0., -0.0, .5, +12e03, -2E+05 ]
Also floats: [
  .inf, -.Inf, +.INF, .NAN ]
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(7, $yaml);
        $this->assertArrayHasKey('A null', $yaml);
        $this->assertNull($yaml['A null']);
        $this->assertArrayHasKey('Also a null', $yaml);
        $this->assertNull($yaml['Also a null']);
        $this->assertArrayHasKey('Not a null', $yaml);
        $this->assertEquals('', $yaml['Not a null']);
        $this->assertArrayHasKey('Booleans', $yaml);
        $this->assertInstanceOf(\ArrayObject::class, $yaml['Booleans']);
        $this->assertCount(4, $yaml['Booleans']);
        $this->assertTrue($yaml['Booleans'][0]);
        $this->assertTrue($yaml['Booleans'][1]);
        $this->assertFalse($yaml['Booleans'][2]);
        $this->assertFalse($yaml['Booleans'][3]);
        $this->assertArrayHasKey('Integers', $yaml);
        $this->assertInstanceOf(\ArrayObject::class, $yaml['Integers']);
        $this->assertCount(4, $yaml['Integers']);
        $this->assertEquals(0, $yaml['Integers'][0]);
        $this->assertEquals(7, $yaml['Integers'][1]);
        $this->assertEquals(58, $yaml['Integers'][2]);
        $this->assertEquals(-19, $yaml['Integers'][3]);
        $this->assertArrayHasKey('Floats', $yaml);
        $this->assertInstanceOf(\ArrayObject::class, $yaml['Floats']);
        $this->assertCount(5, $yaml['Floats']);
        $this->assertEquals(0.0, $yaml['Floats'][0]);
        $this->assertEquals(-0.0, $yaml['Floats'][1]);
        $this->assertEquals(0.5, $yaml['Floats'][2]);
        $this->assertEquals(12000.0, $yaml['Floats'][3]);
        $this->assertEquals(-200000.0, $yaml['Floats'][4]);
        $this->assertArrayHasKey('Also floats', $yaml);
        $this->assertInstanceOf(\ArrayObject::class, $yaml['Also floats']);
        $this->assertCount(4, $yaml['Also floats']);
        $this->assertEquals(INF, $yaml['Also floats'][0]);
        $this->assertEquals(-INF, $yaml['Also floats'][1]);
        $this->assertEquals(INF, $yaml['Also floats'][2]);
        $this->assertNan($yaml['Also floats'][3]);
    }
}
