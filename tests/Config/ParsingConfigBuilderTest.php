<?php

namespace MaxBeckers\YamlParser\Tests\Config;

use MaxBeckers\YamlParser\Config\ParsingConfigBuilder;
use PHPUnit\Framework\TestCase;

class ParsingConfigBuilderTest extends TestCase
{
    public function testBuild_returnsConfigWithDefaults(): void
    {
        $config = ParsingConfigBuilder::create()->build();

        $this->assertTrue($config->strictMode);
        $this->assertFalse($config->returnPlainArrays);
        $this->assertNull($config->maxDepth);
        $this->assertNull($config->maxFileSize);
        $this->assertFalse($config->lazyResolution);
        $this->assertFalse($config->preserveMetadata);
        $this->assertTrue($config->releaseConsumedTokens);
    }

    public function testBuild_returnsConfigWithExplicitValues(): void
    {
        $config = ParsingConfigBuilder::create()
            ->withStrictMode(false)
            ->withReturnPlainArrays(true)
            ->withMaxDepth(12)
            ->withMaxFileSize(2048)
            ->withLazyResolution(true)
            ->withPreserveMetadata(true)
            ->withReleaseConsumedTokens(false)
            ->build();

        $this->assertFalse($config->strictMode);
        $this->assertTrue($config->returnPlainArrays);
        $this->assertSame(12, $config->maxDepth);
        $this->assertSame(2048, $config->maxFileSize);
        $this->assertTrue($config->lazyResolution);
        $this->assertTrue($config->preserveMetadata);
        $this->assertFalse($config->releaseConsumedTokens);
    }

    public function testWithMaxDepth_throwsOnInvalidValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('maxDepth must be greater than 0 when provided.');

        ParsingConfigBuilder::create()->withMaxDepth(0);
    }

    public function testWithMaxFileSize_throwsOnInvalidValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('maxFileSize must be greater than 0 when provided.');

        ParsingConfigBuilder::create()->withMaxFileSize(0);
    }
}
