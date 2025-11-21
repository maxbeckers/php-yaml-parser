<?php

namespace MaxBeckers\YamlParser\Tests;

use MaxBeckers\YamlParser\YamlParser;
use PHPUnit\Framework\TestCase;

class YamlParserMergeTest extends TestCase
{
    private YamlParser $yamlParser;

    protected function setUp(): void
    {
        $this->yamlParser = new YamlParser();
    }

    public function testParseYaml_withSimpleMerge()
    {
        $input = <<<'YAML'
defaults: &default_settings
  retries: 3
  timeout: 30

server1:
  <<: *default_settings  # Merges all key-value pairs from default_settings
  host: example.com  # This key is added to the merged data
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(2, $yaml);
        $this->assertEquals(3, $yaml['server1']['retries']);
        $this->assertEquals(30, $yaml['server1']['timeout']);
        $this->assertEquals('example.com', $yaml['server1']['host']);
    }

    public function testParseYaml_withSimpleMergeAndSequenceMerge()
    {
        $input = <<<'YAML'
---
- &CENTER { x: 1, y: 2 }
- &LEFT { x: 0, y: 2 }
- &BIG { r: 10 }
- &SMALL { r: 1 }

- # Explicit keys
  x: 1
  y: 2
  r: 10
  label: nomerge

- # Merge one map
  << : *CENTER
  r: 10
  label: center

- # Merge multiple maps
  << : [ *CENTER, *BIG ]
  label: center/big

- # Merge multiple maps
  << : [ *CENTER, *LEFT ]
  r: 4
  label: center/left

- # Override
  << : [ *BIG, *LEFT, *SMALL ]
  x: 2
  label: center/left/small
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(9, $yaml);
        $this->assertEquals(1, $yaml[4]['x']);
        $this->assertEquals(2, $yaml[4]['y']);
        $this->assertEquals(10, $yaml[4]['r']);
        $this->assertEquals('nomerge', $yaml[4]['label']);
        $this->assertEquals(1, $yaml[5]['x']);
        $this->assertEquals(2, $yaml[5]['y']);
        $this->assertEquals(10, $yaml[5]['r']);
        $this->assertEquals('center', $yaml[5]['label']);
        $this->assertEquals(1, $yaml[6]['x']);
        $this->assertEquals(2, $yaml[6]['y']);
        $this->assertEquals(10, $yaml[6]['r']);
        $this->assertEquals('center/big', $yaml[6]['label']);
        $this->assertEquals(0, $yaml[7]['x']);
        $this->assertEquals(2, $yaml[7]['y']);
        $this->assertEquals(4, $yaml[7]['r']);
        $this->assertEquals('center/left', $yaml[7]['label']);
        $this->assertEquals(2, $yaml[8]['x']);
        $this->assertEquals(2, $yaml[8]['y']);
        $this->assertEquals(1, $yaml[8]['r']);
        $this->assertEquals('center/left/small', $yaml[8]['label']);
    }

    public function testParseYaml_withExplicitKeyAlwaysWins()
    {
        $input = <<<'YAML'
defaults: &default_settings
  retries: 3
  timeout: 30

server1:
  host: example.com  # This key is added to the merged data
  timeout: 60     # This key overrides the merged data
  <<: *default_settings  # Merges all key-value pairs from default_settings
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(2, $yaml);
        $this->assertEquals(3, $yaml['server1']['retries']);
        $this->assertEquals(60, $yaml['server1']['timeout']);
        $this->assertEquals('example.com', $yaml['server1']['host']);
    }

    public function testParseYaml_withListMergeLastWins()
    {
        $input = <<<'YAML'
defaults: &default_settings
  retries: 3
  timeout: 30
prod: &prod_settings
  retries: 10
  timeout: 20

server1:
  host: example.com
  <<: [*default_settings,*prod_settings]
YAML;

        $yaml = $this->yamlParser->parse($input);

        $this->assertInstanceOf(\ArrayObject::class, $yaml);
        $this->assertCount(3, $yaml);
        $this->assertEquals(10, $yaml['server1']['retries']);
        $this->assertEquals(20, $yaml['server1']['timeout']);
        $this->assertEquals('example.com', $yaml['server1']['host']);
    }
}
