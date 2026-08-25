<?php

namespace MaxBeckers\YamlParser\Tests;

use MaxBeckers\YamlParser\YamlParser;
use PHPUnit\Framework\TestCase;

class YamlParserTest extends TestCase
{
    public function testParsePlainArray_forcesPlainArrayModeWithoutConstructorFlag(): void
    {
        $input = <<<'YAML'
person:
  name: John
  tags:
    - admin
    - active
YAML;

        $yamlParser = new YamlParser();
        $yaml = $yamlParser->parsePlainArray($input);

        $this->assertIsArray($yaml);
        $this->assertIsArray($yaml['person']);
        $this->assertEquals(['admin', 'active'], $yaml['person']['tags']);
    }

    public function testParseFilePlainArray_returnsArrayForNonCircularStructures(): void
    {
        $input = <<<'YAML'
key: value
list:
  - one
  - two
YAML;

        $tmpFile = tempnam(sys_get_temp_dir(), 'yaml-parser-');
        file_put_contents($tmpFile, $input);

        try {
            $yamlParser = new YamlParser();
            $yaml = $yamlParser->parseFilePlainArray($tmpFile, true);

            $this->assertIsArray($yaml);
            $this->assertEquals('value', $yaml['key']);
            $this->assertIsArray($yaml['list']);
        } finally {
            @unlink($tmpFile);
        }
    }

    public function testParseYaml_prefersPlainArraysWithoutCircularReferences(): void
    {
        $input = <<<'YAML'
person:
  name: John
  tags:
    - admin
    - active
YAML;

        $yamlParser = new YamlParser(preferPlainArrays: true);
        $yaml = $yamlParser->parse($input);

        $this->assertIsArray($yaml);
        $this->assertArrayHasKey('person', $yaml);
        $this->assertIsArray($yaml['person']);
        $this->assertEquals('John', $yaml['person']['name']);
        $this->assertIsArray($yaml['person']['tags']);
        $this->assertEquals(['admin', 'active'], $yaml['person']['tags']);
    }

    public function testParseYaml_keepsArrayObjectForCircularReferenceNodes(): void
    {
        $input = <<<'YAML'
person: &person
  name: John
  spouse: *spouse

spouse: &spouse
  name: Jane
  spouse: *person
YAML;

        $yamlParser = new YamlParser(preferPlainArrays: true);
        $yaml = $yamlParser->parse($input);

        $this->assertIsArray($yaml);
        $this->assertInstanceOf(\ArrayObject::class, $yaml['person']);
        $this->assertInstanceOf(\ArrayObject::class, $yaml['person']['spouse']);
        $this->assertSame($yaml['person'], $yaml['person']['spouse']['spouse']);
        $this->assertEquals('John', $yaml['person']['spouse']['spouse']['name']);
    }

    public function testParsePlainArray_keepsArrayObjectForCircularReferenceNodes(): void
    {
        $input = <<<'YAML'
person: &person
  name: John
  spouse: *spouse

spouse: &spouse
  name: Jane
  spouse: *person
YAML;

        $yamlParser = new YamlParser();
        $yaml = $yamlParser->parsePlainArray($input);

        $this->assertIsArray($yaml);
        $this->assertInstanceOf(\ArrayObject::class, $yaml['person']);
        $this->assertInstanceOf(\ArrayObject::class, $yaml['person']['spouse']);
        $this->assertSame($yaml['person'], $yaml['person']['spouse']['spouse']);
    }
}
