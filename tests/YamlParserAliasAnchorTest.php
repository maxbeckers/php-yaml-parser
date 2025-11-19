<?php

namespace MaxBeckers\YamlParser\Tests;

use MaxBeckers\YamlParser\YamlParser;
use PHPUnit\Framework\TestCase;

class YamlParserAliasAnchorTest extends TestCase
{
    private YamlParser $yamlParser;

    protected function setUp(): void
    {
        $this->yamlParser = new YamlParser();
    }

    public function testParseYaml_withAlias()
    {
        $input = <<<'YAML'
key1:
  key11: &alias
    keyAlias1: test
    keyAlias2: "value"

key2:
  key21: *alias
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(2, $yaml);
        $this->assertArrayHasKey('key1', $yaml);
        $this->assertCount(1, $yaml['key1']);
        $this->assertArrayHasKey('key11', $yaml['key1']);
        $this->assertCount(2, $yaml['key1']['key11']);
        $this->assertArrayHasKey('key2', $yaml);
        $this->assertCount(1, $yaml['key2']);
        $this->assertArrayHasKey('key21', $yaml['key2']);
        $this->assertCount(2, $yaml['key2']['key21']);
    }

    public function testParseYaml_withNodePropertyIndicators()
    {
        $input = <<<'YAML'
anchored: !local &anchor value
alias: *anchor
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(2, $yaml);
        $this->assertArrayHasKey('anchored', $yaml);
        $this->assertEquals('value', $yaml['anchored']);
        $this->assertArrayHasKey('alias', $yaml);
        $this->assertEquals('value', $yaml['alias']);
    }

    public function testParseYaml_withCircularReferenceAndTag()
    {
        $input = <<<'YAML'
!!str &a1 "foo":
  !!str bar
&a2 baz : *a1
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(2, $yaml);
        $this->assertArrayHasKey('foo', $yaml);
        $this->assertEquals('bar', $yaml['foo']);
        $this->assertArrayHasKey('baz', $yaml);
        $this->assertArrayHasKey('foo', $yaml['baz']);
    }

    public function testParseYaml_withNodeAnchors()
    {
        $input = <<<'YAML'
First occurrence: &anchor Value
Second occurrence: *anchor
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(2, $yaml);
        $this->assertArrayHasKey('First occurrence', $yaml);
        $this->assertEquals('Value', $yaml['First occurrence']);
        $this->assertArrayHasKey('Second occurrence', $yaml);
        $this->assertEquals('Value', $yaml['Second occurrence']);
    }

    public function testParseYaml_withCircularReference()
    {
        $input = <<<'YAML'
person: &person
  name: John
  spouse: *spouse

spouse: &spouse
  name: Jane
  spouse: *person
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(2, $yaml);
        $this->assertArrayHasKey('person', $yaml);
        $this->assertArrayHasKey('name', $yaml['person']);
        $this->assertEquals('John', $yaml['person']['name']);
        $this->assertArrayHasKey('spouse', $yaml['person']);
        $this->assertArrayHasKey('name', $yaml['person']['spouse']);
        $this->assertEquals('Jane', $yaml['person']['spouse']['name']);
        $this->assertArrayHasKey('spouse', $yaml);
        $this->assertArrayHasKey('name', $yaml['spouse']);
        $this->assertEquals('Jane', $yaml['spouse']['name']);
        $this->assertArrayHasKey('spouse', $yaml['spouse']);
        $this->assertArrayHasKey('name', $yaml['spouse']['spouse']);
        $this->assertEquals('John', $yaml['spouse']['spouse']['name']);
        $this->assertEquals('John', $yaml['spouse']['spouse']['spouse']['spouse']['name']);
        $this->assertEquals('John', $yaml['spouse']['spouse']['spouse']['spouse']['spouse']['spouse']['name']);
        $this->assertEquals('John', $yaml['spouse']['spouse']['spouse']['spouse']['spouse']['spouse']['spouse']['spouse']['name']);
    }

    public function testParseYaml_withAnchorOverwrite()
    {
        $input = <<<'YAML'
First occurrence: &anchor Foo
Second occurrence: *anchor
Override anchor: &anchor Bar
Reuse anchor: *anchor
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(4, $yaml);
        $this->assertArrayHasKey('First occurrence', $yaml);
        $this->assertEquals('Foo', $yaml['First occurrence']);
        $this->assertArrayHasKey('Second occurrence', $yaml);
        $this->assertEquals('Foo', $yaml['Second occurrence']);
        $this->assertArrayHasKey('Override anchor', $yaml);
        $this->assertEquals('Bar', $yaml['Override anchor']);
        $this->assertArrayHasKey('Reuse anchor', $yaml);
        $this->assertEquals('Bar', $yaml['Reuse anchor']);
    }
}
