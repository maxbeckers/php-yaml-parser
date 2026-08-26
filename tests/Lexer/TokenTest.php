<?php

namespace MaxBeckers\YamlParser\Tests\Lexer;

use MaxBeckers\YamlParser\Lexer\Token;
use MaxBeckers\YamlParser\Lexer\TokenType;
use PHPUnit\Framework\TestCase;

final class TokenTest extends TestCase
{
    public function testDefaults_keepMetadataAccessSafeWhenMetadataIsNull(): void
    {
        $token = new Token(TokenType::PLAIN_SCALAR, 'value');

        $this->assertNull($token->metadata);
        $this->assertSame([], $token->getMetadata());
        $this->assertFalse($token->wasMultilineInput());
        $this->assertNull($token->getStartLine());
        $this->assertNull($token->getStartColumn());
        $this->assertSame('fallback', $token->getMetadataValue('missing', 'fallback'));
    }

    public function testMetadataAccessors_readExistingMetadataValues(): void
    {
        $token = new Token(TokenType::PLAIN_SCALAR, 'value', metadata: [
            Token::METADATA_WAS_MULTILINE_INPUT => true,
            Token::METADATA_START_LINE => 12,
            Token::METADATA_START_COLUMN => 3,
            'custom' => 'x',
        ]);

        $this->assertTrue($token->wasMultilineInput());
        $this->assertSame(12, $token->getStartLine());
        $this->assertSame(3, $token->getStartColumn());
        $this->assertSame('x', $token->getMetadataValue('custom'));
    }
}
