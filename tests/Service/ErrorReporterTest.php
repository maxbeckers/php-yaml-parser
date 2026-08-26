<?php

namespace MaxBeckers\YamlParser\Tests\Service;

use MaxBeckers\YamlParser\Config\ParsingConfig;
use MaxBeckers\YamlParser\Node\NodeMetadata;
use MaxBeckers\YamlParser\Service\ErrorReporter;
use MaxBeckers\YamlParser\YamlParser;
use PHPUnit\Framework\TestCase;

final class ErrorReporterTest extends TestCase
{
    public function testFormatIncludesLineAndColumnWhenAvailable(): void
    {
        $reporter = new ErrorReporter();

        $message = $reporter->format('Invalid value', new NodeMetadata(line: 12, column: 7));

        $this->assertSame('Invalid value at line 12, column 7', $message);
    }

    public function testFormatForPathUsesMetadataProvider(): void
    {
        $yaml = <<<'YAML'
items:
  - one
YAML;

        $parser = new YamlParser(config: new ParsingConfig(preserveMetadata: true));
        $result = $parser->parse($yaml);

        $provider = $parser->getMetadataProvider();
        $valueWithMetadata = $provider->getValueWithMetadata($result, 'items.0');

        $reporter = new ErrorReporter();
        $pathMessage = $reporter->formatForPath('Invalid list item', $provider, 'items.0');
        $valueMessage = $reporter->formatForValue('Invalid list item', $valueWithMetadata);

        $this->assertSame('Invalid list item at line 2, column 4', $pathMessage);
        $this->assertSame('Invalid list item at line 2, column 4', $valueMessage);
    }

    public function testFormatReturnsOriginalMessageWithoutMetadata(): void
    {
        $reporter = new ErrorReporter();

        $this->assertSame('Something failed', $reporter->format('Something failed'));
    }
}
