<?php

namespace MaxBeckers\YamlParser\Tests;

use MaxBeckers\YamlParser\Exception\LexerException;
use MaxBeckers\YamlParser\YamlParser;
use PHPUnit\Framework\TestCase;

class YamlParserDoubleQuotedEscapesTest extends TestCase
{
    private YamlParser $yamlParser;

    protected function setUp(): void
    {
        $this->yamlParser = new YamlParser();
    }

    public function testDoubleQuoted_escapeNewline(): void
    {
        $result = $this->yamlParser->parse('"hello\\nworld"');

        $this->assertEquals("hello\nworld", $result);
    }

    public function testDoubleQuoted_escapeCarriageReturn(): void
    {
        $result = $this->yamlParser->parse('"hello\\rworld"');

        $this->assertEquals("hello\rworld", $result);
    }

    public function testDoubleQuoted_escapeBackslash(): void
    {
        $result = $this->yamlParser->parse('"path\\\\to\\\\file"');

        $this->assertEquals('path\\to\\file', $result);
    }

    public function testDoubleQuoted_escapeDoubleQuote(): void
    {
        $result = $this->yamlParser->parse('"say \\"hello\\""');

        $this->assertEquals('say "hello"', $result);
    }

    public function testDoubleQuoted_escapeNullByte(): void
    {
        $result = $this->yamlParser->parse('"null\\0byte"');

        $this->assertEquals("null\x00byte", $result);
    }

    public function testDoubleQuoted_escapeBell(): void
    {
        $result = $this->yamlParser->parse('"bell\\achar"');

        $this->assertEquals("bell\x07char", $result);
    }

    public function testDoubleQuoted_escapeBackspace(): void
    {
        $result = $this->yamlParser->parse('"back\\bspace"');

        $this->assertEquals("back\x08space", $result);
    }

    public function testDoubleQuoted_escapeEscape(): void
    {
        $result = $this->yamlParser->parse('"esc\\echar"');

        $this->assertEquals("esc\x1Bchar", $result);
    }

    public function testDoubleQuoted_escapeFormFeed(): void
    {
        $result = $this->yamlParser->parse('"form\\ffeed"');

        $this->assertEquals("form\x0Cfeed", $result);
    }

    public function testDoubleQuoted_escapeVerticalTab(): void
    {
        $result = $this->yamlParser->parse('"vert\\vtab"');

        $this->assertEquals("vert\x0Btab", $result);
    }

    public function testDoubleQuoted_escapeForwardSlash(): void
    {
        $result = $this->yamlParser->parse('"path\\/to\\/file"');

        $this->assertEquals('path/to/file', $result);
    }

    public function testDoubleQuoted_escapeSpace(): void
    {
        $result = $this->yamlParser->parse('"word\\ word"');

        $this->assertEquals('word word', $result);
    }

    public function testDoubleQuoted_escapeNextLine_N(): void
    {
        $result = $this->yamlParser->parse('"line\\Nbreak"');

        $this->assertEquals("line\xC2\x85break", $result);
    }

    public function testDoubleQuoted_escapeNonBreakingSpace_underscore(): void
    {
        $result = $this->yamlParser->parse('"non\\_breaking"');

        $this->assertEquals("non\xC2\xA0breaking", $result);
    }

    public function testDoubleQuoted_escapeLineSeparator_L(): void
    {
        $result = $this->yamlParser->parse('"line\\Lsep"');

        $this->assertEquals("line\xE2\x80\xA8sep", $result);
    }

    public function testDoubleQuoted_escapeParagraphSeparator_P(): void
    {
        $result = $this->yamlParser->parse('"para\\Psep"');

        $this->assertEquals("para\xE2\x80\xA9sep", $result);
    }

    public function testDoubleQuoted_hexEscape(): void
    {
        $result = $this->yamlParser->parse('"\\x41"');

        $this->assertEquals('A', $result);
    }

    public function testDoubleQuoted_hexEscapeLowercase(): void
    {
        $result = $this->yamlParser->parse('"\\x61"');

        $this->assertEquals('a', $result);
    }

    public function testDoubleQuoted_hexEscapeProducesUnicodeCodepoint(): void
    {
        $result = $this->yamlParser->parse('"\\xE9"');

        $this->assertEquals("\xC3\xA9", $result);
    }

    public function testDoubleQuoted_hexEscapeNonAsciiCopyrightSign(): void
    {
        $result = $this->yamlParser->parse('"\\xA9"');

        $this->assertEquals("\xC2\xA9", $result);
    }

    public function testDoubleQuoted_hexEscapeTwoCodepointsAreTwoCodpoints(): void
    {
        $result = $this->yamlParser->parse('"\\xC3\\xA9"');

        $this->assertEquals("\xC3\x83\xC2\xA9", $result);
    }

    public function testDoubleQuoted_unicodeFourDigit(): void
    {
        $result = $this->yamlParser->parse('"\\u0041"');

        $this->assertEquals('A', $result);
    }

    public function testDoubleQuoted_unicodeFourDigit_nonAscii(): void
    {
        $result = $this->yamlParser->parse('"\\u00E9"');

        $this->assertEquals("\xC3\xA9", $result);
    }

    public function testDoubleQuoted_unicodeFourDigit_threeByteChar(): void
    {
        $result = $this->yamlParser->parse('"\\u4E2D"');

        $this->assertEquals("\xE4\xB8\xAD", $result);
    }

    public function testDoubleQuoted_unicodeEightDigit(): void
    {
        $result = $this->yamlParser->parse('"\\U00000041"');

        $this->assertEquals('A', $result);
    }

    public function testDoubleQuoted_unicodeEightDigit_emoji(): void
    {
        $result = $this->yamlParser->parse('"\\U0001F600"');

        $this->assertEquals("\xF0\x9F\x98\x80", $result);
    }

    public function testDoubleQuoted_multipleEscapesInOneString(): void
    {
        $result = $this->yamlParser->parse('"tab:\\t newline:\\n quote:\\"done\\""');

        $this->assertEquals("tab:\t newline:\n quote:\"done\"", $result);
    }

    public function testDoubleQuoted_invalidEscapeSequenceThrows(): void
    {
        $this->expectException(LexerException::class);
        $this->expectExceptionMessageMatches('/Invalid escape sequence/');

        $this->yamlParser->parse('"invalid\\q escape"');
    }

    public function testDoubleQuoted_invalidEscapeSequence_p_lowercase(): void
    {
        $this->expectException(LexerException::class);
        $this->expectExceptionMessageMatches('/Invalid escape sequence/');

        $this->yamlParser->parse('"\\p is not valid"');
    }

    public function testDoubleQuoted_invalidUnicodeSurrogate(): void
    {
        $this->expectException(LexerException::class);
        $this->expectExceptionMessageMatches('/Invalid Unicode codepoint/');

        $this->yamlParser->parse('"\\uD800"');
    }

    public function testDoubleQuoted_unclosedStringThrows(): void
    {
        $this->expectException(LexerException::class);
        $this->expectExceptionMessageMatches('/Unclosed double-quoted scalar/');

        $this->yamlParser->parse('"never closed');
    }
}
