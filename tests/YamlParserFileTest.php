<?php

namespace MaxBeckers\YamlParser\Tests;

use MaxBeckers\YamlParser\YamlParser;
use PHPUnit\Framework\TestCase;

class YamlParserFileTest extends TestCase
{
    private YamlParser $yamlParser;

    protected function setUp(): void
    {
        $this->yamlParser = new YamlParser();
    }

    public function testParseFile_withOpenApiCompare()
    {
        $json = __DIR__ . \DIRECTORY_SEPARATOR . 'Fixtures' . \DIRECTORY_SEPARATOR . 'openapi' . \DIRECTORY_SEPARATOR . 'openapi.json';
        $yaml = __DIR__ . \DIRECTORY_SEPARATOR . 'Fixtures' . \DIRECTORY_SEPARATOR . 'openapi' . \DIRECTORY_SEPARATOR . 'openapi.yaml';
        $jsonParsed = $this->yamlParser->parseFile($json);
        $yamlParsed = $this->yamlParser->parseFile($yaml);
        $this->assertEquals($jsonParsed, $yamlParsed);
    }

    /**
     * @dataProvider yamlFilesProvider
     */
    public function testParseFile(string $file)
    {
        // Uncomment to see which file is being parsed
        // echo 'Parsing file: ' . $file . PHP_EOL;
        $yaml = $this->yamlParser->parseFile($file);
        $this->assertInstanceOf(\ArrayObject::class, $yaml);
    }

    public static function yamlFilesProvider(): array
    {
        $files = glob(__DIR__ . \DIRECTORY_SEPARATOR . 'Fixtures' . \DIRECTORY_SEPARATOR . '*.yaml');

        $testFiles = [];
        foreach ($files as $file) {
            $testFiles[] = [$file];
        }

        return $testFiles;
    }
}
