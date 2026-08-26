<?php

namespace MaxBeckers\YamlParser\Tests\Lexer;

use MaxBeckers\YamlParser\Lexer\Token;
use MaxBeckers\YamlParser\Lexer\TokenStream;
use MaxBeckers\YamlParser\Lexer\TokenType;
use PHPUnit\Framework\TestCase;

final class TokenStreamTest extends TestCase
{
    public function testConsume_releasesPreviouslyConsumedTokenWhenEnabled(): void
    {
        $stream = new TokenStream([
            new Token(TokenType::PLAIN_SCALAR, 'first'),
            new Token(TokenType::PLAIN_SCALAR, 'second'),
            new Token(TokenType::EOF),
        ]);

        $stream->consume(TokenType::PLAIN_SCALAR);
        $stream->consume(TokenType::PLAIN_SCALAR);

        $all = $stream->all();
        $this->assertCount(2, $all);
        $this->assertSame('second', $all[0]->value);
    }

    public function testSkip_releasesPreviouslyConsumedTokenWhenEnabled(): void
    {
        $stream = new TokenStream([
            new Token(TokenType::NEWLINE),
            new Token(TokenType::NEWLINE),
            new Token(TokenType::PLAIN_SCALAR, 'value'),
            new Token(TokenType::EOF),
        ]);

        $stream->skip(TokenType::NEWLINE);

        $all = $stream->all();
        $this->assertCount(3, $all);
        $this->assertSame(TokenType::NEWLINE, $all[0]->type);
        $this->assertSame('value', $all[1]->value);
    }

    public function testNext_doesNotReleaseWhenDisabled(): void
    {
        $stream = new TokenStream([
            new Token(TokenType::PLAIN_SCALAR, 'first'),
            new Token(TokenType::PLAIN_SCALAR, 'second'),
            new Token(TokenType::EOF),
        ], releaseConsumedTokens: false);

        $stream->next();
        $stream->next();

        $all = $stream->all();
        $this->assertCount(3, $all);
        $this->assertSame('first', $all[0]->value);
    }
}
