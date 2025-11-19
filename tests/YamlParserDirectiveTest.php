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

        // should we ignore the invalid version with a warning instead?
        $this->expectException(LexerException::class);
        $this->expectExceptionMessage('Unsupported YAML version \'1.3\' in line 1, column 0');
        $this->yamlParser->parse($input);
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

        // should we ignore the invalid directive with a warning instead?
        $this->expectException(LexerException::class);
        $this->expectExceptionMessage('Invalid directive name \'FOO\' in line 1, column 0');
        $this->yamlParser->parse($input);
    }
}
