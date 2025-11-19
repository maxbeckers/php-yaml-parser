<?php

namespace MaxBeckers\YamlParser\Tests;

use MaxBeckers\YamlParser\YamlParser;
use PHPUnit\Framework\TestCase;

class YamlParserPlainTextTest extends TestCase
{
    private YamlParser $yamlParser;

    protected function setUp(): void
    {
        $this->yamlParser = new YamlParser();
    }

    public function testParseYaml_WithDoubleQuotedLineBreaks()
    {
        $input = <<<'YAML'
"folded
to a space,

to a line feed, or
  non-content"
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertEquals("folded to a space,\nto a line feed, or non-content", $yaml);
    }

    public function testParseYaml_WithDoubleQuotedLines()
    {
        $input = <<<'YAML'
" 1st non-empty

 2nd non-empty
<tab>3rd non-empty"
YAML;

        $input = str_replace('<tab>', "\t", $input);
        $yaml = $this->yamlParser->parse($input);

        $this->assertEquals(" 1st non-empty\n2nd non-empty 3rd non-empty", $yaml);
    }

    public function testParseYaml_WithSingleQuotedCharacters()
    {
        $input = <<<'YAML'
'here''s to "quotes"'
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertEquals("here's to \"quotes\"", $yaml);
    }

    public function testParseYaml_WithSingleQuotedLines()
    {
        $input = <<<'YAML'
' 1st non-empty

 2nd non-empty
<tab>3rd non-empty '
YAML;
        $input = str_replace('<tab>', "\t", $input);
        $yaml = $this->yamlParser->parse($input);

        $this->assertEquals(" 1st non-empty\n2nd non-empty 3rd non-empty ", $yaml);
    }

    public function testParseYaml_WithPlainLines()
    {
        $input = <<<'YAML'
 1st non-empty

 2nd non-empty
<tab>3rd non-empty
YAML;

        $input = str_replace('<tab>', "\t", $input);
        $yaml = $this->yamlParser->parse($input);

        $this->assertEquals("1st non-empty\n2nd non-empty 3rd non-empty", $yaml);
    }
}
