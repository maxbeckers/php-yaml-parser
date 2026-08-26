<?php

namespace MaxBeckers\YamlParser\Tests\Node;

use MaxBeckers\YamlParser\Node\NodeMetadata;
use PHPUnit\Framework\TestCase;

final class NodeMetadataTest extends TestCase
{
    public function testWithMethodsReturnNewMetadataWithoutMutatingOriginal(): void
    {
        $metadata = new NodeMetadata();

        $enriched = $metadata
            ->withTag('!app/User')
            ->withAnchor('user_anchor')
            ->withAlias('user_alias')
            ->withIsMergeKey()
            ->withPosition(5, 8);

        $this->assertNull($metadata->getTag());
        $this->assertNull($metadata->getAnchor());
        $this->assertNull($metadata->getAlias());
        $this->assertFalse($metadata->isMergeKey());
        $this->assertNull($metadata->getLine());
        $this->assertNull($metadata->getColumn());

        $this->assertSame('!app/User', $enriched->getTag());
        $this->assertSame('user_anchor', $enriched->getAnchor());
        $this->assertSame('user_alias', $enriched->getAlias());
        $this->assertTrue($enriched->isMergeKey());
        $this->assertSame(5, $enriched->getLine());
        $this->assertSame(8, $enriched->getColumn());
    }

    public function testCustomMetadataCanBeExtended(): void
    {
        $metadata = (new NodeMetadata())
            ->withCustomMetadata('token_index', 42)
            ->withCustomMetadata('source_file', 'spec.yaml');

        $this->assertTrue($metadata->hasCustomMetadata('token_index'));
        $this->assertSame(42, $metadata->getCustomMetadata('token_index'));
        $this->assertSame('spec.yaml', $metadata->getCustomMetadata('source_file'));
        $this->assertSame('fallback', $metadata->getCustomMetadata('missing', 'fallback'));

        $all = $metadata->getCustomMetadataMap();
        $this->assertSame(42, $all['token_index']);
        $this->assertSame('spec.yaml', $all['source_file']);
    }
}
