<?php

namespace MaxBeckers\YamlParser\Tests;

use MaxBeckers\YamlParser\Config\ParsingConfig;
use MaxBeckers\YamlParser\Exception\LexerException;
use MaxBeckers\YamlParser\Exception\ResolverException;
use MaxBeckers\YamlParser\YamlParser;
use PHPUnit\Framework\TestCase;

class YamlParserErrorHandlingTest extends TestCase
{
    private YamlParser $yamlParser;

    protected function setUp(): void
    {
        $this->yamlParser = new YamlParser();
    }

    public function testUndefinedAliasThrowsResolverException(): void
    {
        $input = "key: *undefinedAnchor\n";

        $this->expectException(ResolverException::class);
        $this->expectExceptionMessageMatches('/Unknown alias: \*undefinedAnchor/');

        $this->yamlParser->parse($input);
    }

    public function testAliasOnUndefinedAnchorInFlowContextThrows(): void
    {
        $input = "[*missing, 1, 2]\n";

        $this->expectException(ResolverException::class);
        $this->expectExceptionMessageMatches('/Unknown alias: \*missing/');

        $this->yamlParser->parse($input);
    }

    public function testBlockScalarIndentationIndicatorZeroThrows(): void
    {
        $input = "key: |0\n  value\n";

        $this->expectException(LexerException::class);
        $this->expectExceptionMessageMatches('/indentation indicator cannot be 0/');

        $this->yamlParser->parse($input);
    }

    public function testFoldedBlockScalarIndentationIndicatorZeroThrows(): void
    {
        $input = "key: >0\n  value\n";

        $this->expectException(LexerException::class);
        $this->expectExceptionMessageMatches('/indentation indicator cannot be 0/');

        $this->yamlParser->parse($input);
    }

    public function testBlockScalarIndentationIndicatorAboveNineThrows(): void
    {
        $this->expectException(LexerException::class);

        $parser = new YamlParser();
        $parser->parse("|99\n" . str_repeat(' ', 9) . "value\n");
    }

    public function testTabInBlockIndentationThrowsLexerException(): void
    {
        $input = "key:\n\t- item\n";

        $this->expectException(LexerException::class);
        $this->expectExceptionMessageMatches('/Tab character found in block indentation/');


        $this->yamlParser->parse($input);
    }

    public function testRepeatedYamlDirectiveThrowsLexerException(): void
    {
        $input = "%YAML 1.2\n%YAML 1.1\nfoo\n";

        $this->expectException(LexerException::class);
        $this->expectExceptionMessageMatches('/YAML directive already defined/');

        $this->yamlParser->parse($input);
    }

    public function testParseFileNonExistentPathThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/File not found/');

        $this->yamlParser->parseFile('/nonexistent/path/to/file.yaml');
    }

    public function testMaxFileSizeZeroThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/maxFileSize must be greater than 0/');

        new YamlParser(config: new ParsingConfig(maxFileSize: 0));
    }

    public function testMaxDepthZeroThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/maxDepth must be greater than 0/');

        new YamlParser(config: new ParsingConfig(maxDepth: 0));
    }

    public function testGetLastAstWithoutPreserveMetadataReturnsNull(): void
    {
        $this->yamlParser->parse("key: value\n");

        $this->assertNull($this->yamlParser->getLastAst());
    }

    public function testGetMetadataProviderWithoutPriorParseThrowsLogicException(): void
    {
        $parserWithMeta = new YamlParser(config: new ParsingConfig(preserveMetadata: true));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/Metadata is not available/');

        $parserWithMeta->getMetadataProvider();
    }
}
