<?php

namespace MaxBeckers\YamlParser\Tests;

use MaxBeckers\YamlParser\YamlParser;
use PHPUnit\Framework\TestCase;

class YamlParserDocumentsTest extends TestCase
{
    private YamlParser $yamlParser;

    protected function setUp(): void
    {
        $this->yamlParser = new YamlParser();
    }

    public function testParseYaml_withTwoDocumentsInAStream()
    {
        $input = <<<'YAML'
# Ranking of 1998 home runs
---
- Mark McGwire
- Sammy Sosa
- Ken Griffey

# Team ranking
---
- Chicago Cubs
- St Louis Cardinals
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(2, $yaml);
        $this->assertCount(3, $yaml[0]);
        $this->assertEquals('Mark McGwire', $yaml[0][0]);
        $this->assertEquals('Sammy Sosa', $yaml[0][1]);
        $this->assertEquals('Ken Griffey', $yaml[0][2]);
        $this->assertCount(2, $yaml[1]);
        $this->assertEquals('Chicago Cubs', $yaml[1][0]);
        $this->assertEquals('St Louis Cardinals', $yaml[1][1]);
    }

    public function testParseYaml_withTwoDocumentsInAStreamWithEndings()
    {
        $input = <<<'YAML'
---
time: 20:03:20
player: Sammy Sosa
action: strike (miss)
...
---
time: 20:03:47
player: Sammy Sosa
action: grand slam
...
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(2, $yaml);
        $this->assertCount(3, $yaml[0]);
        $this->assertArrayHasKey('time', $yaml[0]);
        $this->assertArrayHasKey('player', $yaml[0]);
        $this->assertArrayHasKey('action', $yaml[0]);
        $this->assertEquals('20:03:20', $yaml[0]['time']);
        $this->assertEquals('Sammy Sosa', $yaml[0]['player']);
        $this->assertEquals('strike (miss)', $yaml[0]['action']);
        $this->assertCount(3, $yaml[1]);
        $this->assertArrayHasKey('time', $yaml[1]);
        $this->assertArrayHasKey('player', $yaml[1]);
        $this->assertArrayHasKey('action', $yaml[1]);
        $this->assertEquals('20:03:47', $yaml[1]['time']);
        $this->assertEquals('Sammy Sosa', $yaml[1]['player']);
        $this->assertEquals('grand slam', $yaml[1]['action']);
    }

    public function testParseYaml_withDocumentMarkers()
    {
        $input = <<<'YAML'
%YAML 1.2
---
Document
... # Suffix
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertEquals('Document', $yaml);
    }

    public function testParseYaml_withBareDocuments()
    {
        $this->markTestSkipped('Currently not supported: Bare documents without document markers.');
        $input = <<<'YAML'
Bare
document
...
# No document
...
|
%!PS-Adobe-2.0 # Not the first line
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(2, $yaml);
        $this->assertEquals('Bare document', $yaml[0]);
        $this->assertEquals("%!PS-Adobe-2.0\n", $yaml[1]);
    }

    public function testParseYaml_withExplicitDocuments()
    {
        $this->markTestSkipped('Currently not supported: Explicit documents with document markers.');
        $input = <<<'YAML'
---
{ matches
% : 20 }
...
---
# Empty
...
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(2, $yaml);
        $this->assertArrayHasKey('matches %', $yaml[0]);
        $this->assertEquals(20, $yaml[0]['matches %']);
        $this->assertNull($yaml[1]);
    }

    public function testParseYaml_withDirectivesDocuments()
    {
        $this->markTestSkipped('Second document with only directives and comment is not correctly parsed.');
        $input = <<<'YAML'
%YAML 1.2
--- |
%!PS-Adobe-2.0
...
%YAML 1.2
---
# Empty
...
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(2, $yaml);
        $this->assertEquals("%!PS-Adobe-2.0\n", $yaml[0]);
        $this->assertNull($yaml[1]);
    }

    public function testParseYaml_withStream()
    {
        $this->markTestSkipped('Second document with only comment is not correctly parsed.');
        $input = <<<'YAML'
Document
---
# Empty
...
%YAML 1.2
---
matches %: 20
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(3, $yaml);
        $this->assertEquals('Document', $yaml[0]);
        $this->assertNull($yaml[1]);
        $this->assertArrayHasKey('matches %', $yaml[2]);
        $this->assertEquals(20, $yaml[2]['matches %']);
    }

    public function testParseYaml_withVersionHandling()
    {
        $input = <<<'YAML'
%YAML 1.1
---
isTrue1: true
isTrue2: yes
isTrue3: on
isFalse1: false
isFalse2: no
isFalse3: off
nullValue1: null
nullValue2: ~
nullValue3: ""
...
%YAML 1.2
---
isTrue1: true
isTrue2: yes
isTrue3: on
isFalse1: false
isFalse2: no
isFalse3: off
nullValue1: null
nullValue2: ~
nullValue3: ""
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(2, $yaml);
        // YAML 1.1
        $this->assertTrue($yaml[0]['isTrue1']);
        $this->assertTrue($yaml[0]['isTrue2']);
        $this->assertTrue($yaml[0]['isTrue3']);
        $this->assertFalse($yaml[0]['isFalse1']);
        $this->assertFalse($yaml[0]['isFalse2']);
        $this->assertFalse($yaml[0]['isFalse3']);
        $this->assertNull($yaml[0]['nullValue1']);
        $this->assertNull($yaml[0]['nullValue2']);
        $this->assertNull($yaml[0]['nullValue3']);
        // YAML 1.2
        $this->assertTrue($yaml[1]['isTrue1']);
        $this->assertEquals('yes', $yaml[1]['isTrue2']);
        $this->assertEquals('on', $yaml[1]['isTrue3']);
        $this->assertFalse($yaml[1]['isFalse1']);
        $this->assertEquals('no', $yaml[1]['isFalse2']);
        $this->assertEquals('off', $yaml[1]['isFalse3']);
        $this->assertNull($yaml[1]['nullValue1']);
        $this->assertNull($yaml[1]['nullValue2']);
        $this->assertEquals('', $yaml[1]['nullValue3']);
    }
}
