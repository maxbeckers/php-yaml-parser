<?php

namespace MaxBeckers\YamlParser\Tests;

use MaxBeckers\YamlParser\Config\ParsingConfig;
use MaxBeckers\YamlParser\Exception\ParserException;
use MaxBeckers\YamlParser\YamlParser;
use PHPUnit\Framework\TestCase;

final class YamlParserConfigOptionsTest extends TestCase
{
    public function testMaxFileSizeLimitThrowsWhenFileIsTooLarge(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'yaml-max-size-');
        file_put_contents($tmpFile, "key: value\n");

        try {
            $yamlParser = new YamlParser(config: new ParsingConfig(maxFileSize: 4));

            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('exceeds configured limit');

            $yamlParser->parseFile($tmpFile);
        } finally {
            @unlink($tmpFile);
        }
    }

    public function testMaxDepthLimitThrowsForDeeplyNestedYaml(): void
    {
        $yaml = <<<'YAML'
a:
  b:
    c:
      d: value
YAML;

        $yamlParser = new YamlParser(config: new ParsingConfig(maxDepth: 3));

        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Maximum parsing depth of 3 exceeded');

        $yamlParser->parse($yaml);
    }

    public function testStrictModeTrueRejectsMalformedNestedMappingInScalarValue(): void
    {
        $yaml = "a: b: c\n";

        $strictParser = new YamlParser(config: new ParsingConfig(strictMode: true));
        $this->expectException(ParserException::class);
        $strictParser->parse($yaml);
    }

    public function testStrictModeFalseParsesMalformedNestedMappingInScalarValue(): void
    {
        $yaml = "a: b: c\n";

        $lenientParser = new YamlParser(config: new ParsingConfig(strictMode: false));
        $result = $lenientParser->parse($yaml);

        $this->assertNotNull($result);
    }

    public function testMalformedScalarLikeMappingRemainsRejectedInStrictParsing(): void
    {
        $yaml = "a: b: c\n";

        $parser = new YamlParser(config: new ParsingConfig(strictMode: true));
        $this->expectException(ParserException::class);
        $parser->parse($yaml);
    }

    public function testResolvesAliasesWhenNeeded(): void
    {
        $yaml = <<<'YAML'
base: &base
  value: 42
copy: *base
YAML;

        $yamlParser = new YamlParser(config: new ParsingConfig(returnPlainArrays: true));
        $result = $yamlParser->parse($yaml);

        $this->assertIsArray($result);
        $this->assertSame(42, $result['copy']['value']);
    }

    public function testPreserveMetadataStoresAstWithSourcePositions(): void
    {
        $yaml = "key: value\n";

        $yamlParser = new YamlParser(config: new ParsingConfig(preserveMetadata: true));
        $yamlParser->parse($yaml);

        $this->assertNotNull($yamlParser->getMetadataProvider()->getMetadata('key')?->getLine());
    }

    public function testStreamingParserParsesEquivalentResultAndPreservesMetadata(): void
    {
        $yaml = <<<'YAML'
meta: &meta
  role: admin
users:
  - name: Alice
    settings: *meta
YAML;

        $regularParser = new YamlParser(config: new ParsingConfig(returnPlainArrays: true));
        $streamingParser = new YamlParser(config: new ParsingConfig(returnPlainArrays: true, preserveMetadata: true));

        $regular = $regularParser->parse($yaml);
        $streaming = $streamingParser->parse($yaml);

        $this->assertSame($regular, $streaming);
        $this->assertNotNull($streamingParser->getMetadataProvider()->getMetadata('meta')?->getLine());
    }

    public function testStreamingParserHandlesNestedStructures(): void
    {
        $yaml = <<<'YAML'
services:
  - name: api
    endpoints:
      - /health
      - /ready
  - name: worker
    queue: critical
YAML;

        $regularParser = new YamlParser(config: new ParsingConfig(returnPlainArrays: true));
        $streamingParser = new YamlParser(config: new ParsingConfig(returnPlainArrays: true));

        $this->assertSame($regularParser->parse($yaml), $streamingParser->parse($yaml));
    }

    public function testDefaultDirectPipelineKeepsEquivalentOutputForValidYaml(): void
    {
        $yaml = <<<'YAML'
services:
  - name: api
    retries: 3
  - name: worker
    retries: 1
YAML;

        $strictParser = new YamlParser(config: new ParsingConfig(returnPlainArrays: true));
        $fastParser = new YamlParser(config: new ParsingConfig(strictMode: false, returnPlainArrays: true));

        $this->assertSame($strictParser->parse($yaml), $fastParser->parse($yaml));
    }

    public function testDefaultDirectPipelineKeepsCommonScalarTypingParity(): void
    {
        $yaml = <<<'YAML'
int_value: 42
float_value: 3.14
bool_value: true
null_value: null
hex_value: 0x10
octal_value: 0o10
string_value: hello
YAML;

        $strictParser = new YamlParser(config: new ParsingConfig(returnPlainArrays: true));
        $fastParser = new YamlParser(config: new ParsingConfig(strictMode: false, returnPlainArrays: true));

        $this->assertSame($strictParser->parse($yaml), $fastParser->parse($yaml));
    }

    public function testDirectPipelineKeepsEquivalentOutputForListMappings(): void
    {
        $yaml = <<<'YAML'
- item_0: value_0
- item_1: value_1
- item_2: value_2
YAML;

        $strictParser = new YamlParser(config: new ParsingConfig(returnPlainArrays: true));
        $directFastParser = new YamlParser(config: new ParsingConfig(strictMode: false, returnPlainArrays: true));

        $this->assertSame($strictParser->parse($yaml), $directFastParser->parse($yaml));
    }

    public function testDirectPipelineSupportsAnchorsAliasesAndMergeKeys(): void
    {
        $yaml = <<<'YAML'
defaults: &defaults
  retries: 3
  timeout: 15
service:
  <<: *defaults
  timeout: 30
YAML;

        $strictParser = new YamlParser(config: new ParsingConfig(returnPlainArrays: true));
        $directFastParser = new YamlParser(config: new ParsingConfig(strictMode: false, returnPlainArrays: true));

        $this->assertSame($strictParser->parse($yaml), $directFastParser->parse($yaml));
    }

    public function testDirectPipelineKeepsParityForFlowCollections(): void
    {
        $yaml = <<<'YAML'
coordinates: [1.5, 2.3, 4.7]
metadata: {created: 2024-01-01, author: john, tags: [tag1, tag2]}
YAML;

        $strictParser = new YamlParser(config: new ParsingConfig(returnPlainArrays: true));
        $directFastParser = new YamlParser(config: new ParsingConfig(strictMode: false, returnPlainArrays: true));

        $this->assertSame($strictParser->parse($yaml), $directFastParser->parse($yaml));
    }

    public function testDirectPipelineKeepsParityForMixedFlowStructures(): void
    {
        $yaml = <<<'YAML'
services:
  - name: api
    ports: [8080, 8081]
    labels: {tier: web, enabled: true}
  - name: worker
    config: {retries: 3, backoff: [1, 2, 5]}
YAML;

        $strictParser = new YamlParser(config: new ParsingConfig(returnPlainArrays: true));
        $fastParserDefaultDirect = new YamlParser(config: new ParsingConfig(strictMode: false, returnPlainArrays: true));

        $this->assertSame($strictParser->parse($yaml), $fastParserDefaultDirect->parse($yaml));
    }

    public function testDirectPipelineKeepsParityForMappingValuesWithBlockSequences(): void
    {
        $yaml = <<<'YAML'
api:
  paths:
    - /health
    - /ready
  tags:
    - internal
    - public
YAML;

        $strictParser = new YamlParser(config: new ParsingConfig(returnPlainArrays: true));
        $directParser = new YamlParser(config: new ParsingConfig(strictMode: false, returnPlainArrays: true));

        $this->assertSame($strictParser->parse($yaml), $directParser->parse($yaml));
    }

    public function testDirectPipelineKeepsParityForUnknownCustomTagValues(): void
    {
        $yaml = "service: !env APP_NAME\n";

        $strictParser = new YamlParser(config: new ParsingConfig(returnPlainArrays: true));
        $directParser = new YamlParser(config: new ParsingConfig(strictMode: false, returnPlainArrays: true));

        $this->assertSame($strictParser->parse($yaml), $directParser->parse($yaml));
        $this->assertSame('APP_NAME', $directParser->parse($yaml)['service']);
    }

    public function testDirectPipelineHonorsYamlVersionDirective11Scalars(): void
    {
        $yaml = <<<'YAML'
%YAML 1.1
---
flags:
  yes_value: yes
  no_value: no
  on_value: on
  off_value: off
YAML;

        $strictParser = new YamlParser(config: new ParsingConfig(returnPlainArrays: true));
        $directParser = new YamlParser(config: new ParsingConfig(strictMode: false, returnPlainArrays: true));

        $this->assertSame($strictParser->parse($yaml), $directParser->parse($yaml));
        $this->assertTrue($directParser->parse($yaml)['flags']['yes_value']);
        $this->assertFalse($directParser->parse($yaml)['flags']['no_value']);
    }

    public function testDirectPipelineSkipsTagDirectiveWithoutFallback(): void
    {
        $yaml = <<<'YAML'
%TAG !yaml! tag:yaml.org,2002:
---
value: 1
YAML;

        $strictParser = new YamlParser(config: new ParsingConfig(returnPlainArrays: true));
        $directParser = new YamlParser(config: new ParsingConfig(strictMode: false, returnPlainArrays: true));

        $this->assertSame($strictParser->parse($yaml), $directParser->parse($yaml));
    }
}
