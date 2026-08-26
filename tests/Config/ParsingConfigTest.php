<?php

namespace MaxBeckers\YamlParser\Tests\Config;

use MaxBeckers\YamlParser\Config\ParsingConfig;
use PHPUnit\Framework\TestCase;

class ParsingConfigTest extends TestCase
{
    public function testDefaults_areApplied(): void
    {
        $config = new ParsingConfig();

        $this->assertTrue($config->strictMode);
        $this->assertFalse($config->returnPlainArrays);
        $this->assertNull($config->maxDepth);
        $this->assertNull($config->maxFileSize);
        $this->assertFalse($config->lazyResolution);
        $this->assertFalse($config->preserveMetadata);
    }

    public function testConstructor_allowsOverridingAllOptions(): void
    {
        $config = new ParsingConfig(
            strictMode: false,
            returnPlainArrays: true,
            maxDepth: 8,
            maxFileSize: 1024,
            lazyResolution: true,
            preserveMetadata: true,
        );

        $this->assertFalse($config->strictMode);
        $this->assertTrue($config->returnPlainArrays);
        $this->assertSame(8, $config->maxDepth);
        $this->assertSame(1024, $config->maxFileSize);
        $this->assertTrue($config->lazyResolution);
        $this->assertTrue($config->preserveMetadata);
    }

    public function testConstructor_throwsOnInvalidMaxDepth(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('maxDepth must be greater than 0 when provided.');

        new ParsingConfig(maxDepth: 0);
    }

    public function testConstructor_throwsOnInvalidMaxFileSize(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('maxFileSize must be greater than 0 when provided.');

        new ParsingConfig(maxFileSize: 0);
    }
}
