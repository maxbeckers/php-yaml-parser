<?php

namespace MaxBeckers\YamlParser\Tests;

use MaxBeckers\YamlParser\Exception\LexerException;
use MaxBeckers\YamlParser\YamlParser;
use PHPUnit\Framework\TestCase;

class YamlParserScalarBlockTest extends TestCase
{
    private YamlParser $yamlParser;

    protected function setUp(): void
    {
        $this->yamlParser = new YamlParser();
    }

    public function testParseYaml_withHtmlToYaml()
    {
        $input = <<<'YAML'
---
example: >
        HTML goes into YAML without modification
message: |

        <blockquote style="font: italic 1em serif">
        <p>"Three is always greater than two,
           even for large values of two"</p>
        <p>--Author Unknown</p>
        </blockquote>
date: 2007-06-01
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(3, $yaml);
        $this->assertArrayHasKey('example', $yaml);
        $this->assertArrayHasKey('message', $yaml);
        $this->assertArrayHasKey('date', $yaml);
        $this->assertEquals("HTML goes into YAML without modification\n", $yaml['example']);
        $this->assertEquals("\n<blockquote style=\"font: italic 1em serif\">\n<p>\"Three is always greater than two,\n   even for large values of two\"</p>\n<p>--Author Unknown</p>\n</blockquote>\n", $yaml['message']);
        $this->assertEquals('2007-06-01', $yaml['date']);
    }

    public function testParseYaml_withLiteralAndFolded()
    {
        $input = <<<'YAML'
literal: |
  some
  text
folded: >
  some
  text
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(2, $yaml);
        $this->assertArrayHasKey('literal', $yaml);
        $this->assertArrayHasKey('folded', $yaml);
        $this->assertEquals("some\ntext\n", $yaml['literal']);
        $this->assertEquals("some text\n", $yaml['folded']);
    }

    public function testParseYamlWithColonInScalarBlock()
    {
        $input = <<<'YAML'
---
description: |-
    This is a test description:
      * It includes colons: like this one.

        And it should be parsed correctly.
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(1, $yaml);
        $this->assertArrayHasKey('description', $yaml);
        $this->assertEquals("This is a test description:\n  * It includes colons: like this one.\n\n    And it should be parsed correctly.", $yaml['description']);
    }

    public function testParseYaml_WithColonInScalarBlock()
    {
        $input = <<<'YAML'
---
key1:
  key11:
    key111: |
      Some text with : colon in it.
      Another line.
key2:
  key21: |-
    This is some text with a test description:
      * something with colon.
      And another line.
  key22: And another value:with colon.
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(2, $yaml);
        $this->assertArrayHasKey('key1', $yaml);
        $this->assertArrayHasKey('key2', $yaml);
        $this->assertArrayHasKey('key11', $yaml['key1']);
        $this->assertArrayHasKey('key111', $yaml['key1']['key11']);
        $this->assertEquals("Some text with : colon in it.\nAnother line.\n", $yaml['key1']['key11']['key111']);
        $this->assertArrayHasKey('key21', $yaml['key2']);
        $this->assertArrayHasKey('key22', $yaml['key2']);
        $this->assertEquals("This is some text with a test description:\n  * something with colon.\n  And another line.", $yaml['key2']['key21']);
        $this->assertEquals('And another value:with colon.', $yaml['key2']['key22']);
    }

    public function testParseYamlMappingScalarsToSequences()
    {
        $input = <<<'YAML'
american:
- Boston Red Sox
- Detroit Tigers
- New York Yankees
national:
- New York Mets
- Chicago Cubs
- Atlanta Braves
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertArrayHasKey('american', $yaml);
        $this->assertInstanceOf(\ArrayObject::class, $yaml['american']);
        $this->assertCount(3, $yaml['american']);
        $this->assertArrayHasKey('national', $yaml);
        $this->assertInstanceOf(\ArrayObject::class, $yaml['national']);
        $this->assertCount(3, $yaml['national']);
    }

    public function testParseYaml_withFoldedAsciiArt()
    {
        $input = <<<'YAML'
# ASCII Art
--- |
  \//||\/||
  // ||  ||__
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertEquals("\//||\/||\n// ||  ||__\n", $yaml);
    }

    public function testParseYaml_withFoldedScalars()
    {
        $input = <<<'YAML'
--- >
  Mark McGwire's
  year was crippled
  by a knee injury.
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertEquals("Mark McGwire's year was crippled by a knee injury.\n", $yaml);
    }

    public function testParseYaml_withFoldedMoreIndentedScalars()
    {
        $input = <<<'YAML'
--- >
 Sammy Sosa completed another
 fine season with great stats.

   63 Home Runs
   0.288 Batting Average

 What a year!
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertEquals("Sammy Sosa completed another fine season with great stats.\n\n  63 Home Runs\n  0.288 Batting Average\n\nWhat a year!\n", $yaml);
    }

    public function testParseYaml_withFoldedAndLiteralScalars()
    {
        $input = <<<'YAML'
name: Mark McGwire
accomplishment: >
  Mark set a major league
  home run record in 1998.
stats: |
  65 Home Runs
  0.278 Batting Average
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertEquals('Mark McGwire', $yaml['name']);
        $this->assertEquals("Mark set a major league home run record in 1998.\n", $yaml['accomplishment']);
        $this->assertEquals("65 Home Runs\n0.278 Batting Average\n", $yaml['stats']);
    }

    public function testParseYaml_withEmptyLines()
    {
        $input = <<<'YAML'
Folding:
  "Empty line
    
  as a line feed"
Chomping: |
  Clipped empty lines
 
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertEquals("Empty line\nas a line feed", $yaml['Folding']);
        $this->assertEquals("Clipped empty lines\n", $yaml['Chomping']);
    }

    public function testParseYaml_withLineFolding()
    {
        $input = <<<'YAML'
>-
  trimmed
  
 

  as
  space
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertEquals("trimmed\n\n\nas space", $yaml);
    }

    public function testParseYaml_withBlockFolding()
    {
        $input = <<<'YAML'
>
  foo 
 
  <tab> bar

  baz
YAML;
        $input = str_replace('<tab>', "\t", $input);

        $yaml = $this->yamlParser->parse($input);

        $this->assertEquals("foo \n\n\t bar\n\nbaz\n", $yaml);
    }

    public function testParseYaml_withFlowFolding()
    {
        $input = <<<'YAML'
"
  foo 
 
  <tab> bar

  baz<nl> "
YAML;
        $input = str_replace(['<tab>', '<nl>'], ["\t", "\n"], $input);

        $yaml = $this->yamlParser->parse($input);

        $this->assertEquals(" foo\nbar\nbaz ", $yaml);
    }

    public function testParseYaml_withBlockScalarHeader()
    {
        $input = <<<'YAML'
- | # Empty header
 literal
- >1 # Indentation indicator
  folded
- |+ # Chomping indicator
 keep

- >1- # Both indicators
  strip
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(4, $yaml);
        $this->assertEquals("literal\n", $yaml[0]);
        $this->assertEquals(" folded\n", $yaml[1]);
        $this->assertEquals("keep\n\n", $yaml[2]);
        $this->assertEquals(' strip', $yaml[3]);
    }

    public function testParseYaml_withBlockIndentationIndicator()
    {
        $input = <<<'YAML'
- |
 detected
- >
 
  
  # detected
- |1
  explicit
- >
 <tab>
 detected
YAML;

        $input = str_replace('<tab>', "\t", $input);
        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(4, $yaml);
        $this->assertEquals("detected\n", $yaml[0]);
        $this->assertEquals("\n\n# detected\n", $yaml[1]);
        $this->assertEquals(" explicit\n", $yaml[2]);
        $this->assertEquals("\t\ndetected\n", $yaml[3]);
    }

    public function testParseYaml_withInvalidBlockScalarIndentationIndicator_WithLeadingEmptyLineTooManySpaces()
    {
        $input = <<<'YAML'
- |
  
 text
YAML;

        $this->expectException(LexerException::class);
        $this->expectExceptionMessage('Literal block scalar indentation less than the defined indentation in line 3, column 0');
        $this->yamlParser->parse($input);
    }

    public function testParseYaml_withInvalidBlockScalarIndentationIndicator_WithFollowingTextLessIndented()
    {
        $input = <<<'YAML'
- >
  text
 text
YAML;

        $this->expectException(LexerException::class);
        $this->expectExceptionMessage('Folded block scalar indentation less than the defined indentation in line 2, column 0');
        $this->yamlParser->parse($input);
    }

    public function testParseYaml_withInvalidBlockScalarIndentationIndicator_WithTooLessIndentedAsIndented()
    {
        $input = <<<'YAML'
- |2
 text
YAML;

        $this->expectException(LexerException::class);
        $this->expectExceptionMessage('Literal block scalar indentation less than the defined indentation in line 2, column 0');
        $this->yamlParser->parse($input);
    }

    public function testParseYaml_withLiteralScalar()
    {
        $input = <<<'YAML'
|
 literal
  text

YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertEquals("literal\n text\n", $yaml);
    }

    public function testParseYaml_withLiteralContent()
    {
        $input = <<<'YAML'
|
 
  
  literal
   
  
  text

 # Comment
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertEquals("\n\nliteral\n \n\ntext\n", $yaml);
    }

    public function testParseYaml_withFoldedScalar()
    {
        $input = <<<'YAML'
>
 folded
 text

YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertEquals("folded text\n", $yaml);
    }

    public function testParseYaml_withFoldedLines()
    {
        $input = <<<'YAML'
>

 folded
 line

 next
 line
   * bullet

   * list
   * lines

 last
 line

# Comment
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertEquals("\nfolded line\nnext line\n  * bullet\n\n  * list\n  * lines\n\nlast line\n", $yaml);
    }

    public function testParseYaml_withExplicitBlockMappingEntries()
    {
        $input = <<<'YAML'
? explicit key # Empty value
? |
  block key
: - one # Explicit compact
  - two # block value
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertEquals(new \ArrayObject([
            'explicit key' => null,
            "block key\n" => new \ArrayObject([
                'one',
                'two',
            ]),
        ]), $yaml);
    }

    public function testParseYaml_withBlockScalarNodes()
    {
        $input = <<<'YAML'
literal: |2
  value
folded:
   !foo
  >1
 value
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(2, $yaml);
        $this->assertArrayHasKey('literal', $yaml);
        $this->assertArrayHasKey('folded', $yaml);
        $this->assertEquals("value\n", $yaml['literal']);
        $this->assertEquals("value\n", $yaml['folded']);
    }
}
