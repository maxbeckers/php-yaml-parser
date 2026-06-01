<?php

namespace MaxBeckers\YamlParser\Tests;

use MaxBeckers\YamlParser\Exception\LexerException;
use MaxBeckers\YamlParser\YamlParser;
use PHPUnit\Framework\TestCase;

class YamlParserDirectiveTest extends TestCase
{
    private YamlParser $yamlParser;

    protected function setUp(): void
    {
        $this->yamlParser = new YamlParser();
    }

    public function testParseYaml_withTwoDocumentsInAStream()
    {
        $input = <<<'YAML'
%YAML 1.2
--- text
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertEquals('text', $yaml);
    }

    public function testParseYaml_withUnsupportedVersion()
    {
        $input = <<<'YAML'
%YAML 1.3
--- text
YAML;

        $yaml = $this->yamlParser->parse($input);
        $this->assertEquals('text', $yaml);
    }

    public function testParseYaml_withYamlDirectiveTwice()
    {
        $input = <<<'YAML'
%YAML 1.2
%YAML 1.1
foo
YAML;

        $this->expectException(LexerException::class);
        $this->expectExceptionMessage('YAML directive already defined earlier in line 2, column 0');
        $this->yamlParser->parse($input);
    }

    public function testParseYaml_withUndefinedDirective()
    {
        $input = <<<'YAML'
%FOO  bar baz # Should be ignored
              # with a warning.
--- "foo"
YAML;

        $yaml = $this->yamlParser->parse($input);
        $this->assertEquals('foo', $yaml);
    }
}
