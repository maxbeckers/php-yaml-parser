<?php

namespace MaxBeckers\YamlParser\Parser\Scan;

use MaxBeckers\YamlParser\Exception\LexerException;
use MaxBeckers\YamlParser\Exception\ParserException;
use MaxBeckers\YamlParser\Format\Version;
use MaxBeckers\YamlParser\Node\NodeMetadata;
use MaxBeckers\YamlParser\Parser\FastScalarResolver;

/**
 * Internal parser module composed into ScanParser.
 *
 * Plain and quoted scalar scanning, escape handling and scalar resolution.
 *
 * This trait is an internal implementation detail of the scanner-parser and
 * is not part of the public API. It operates directly on the scan state
 * declared by ScanParser ($pos, $line, $yaml, ...).
 */
trait ScalarScannerTrait
{
    /**
     * Read a plain scalar value (block context).
     * Returns the raw string (type resolution is done separately).
     */
    private function readPlainScalar(int $contextIndent): string
    {
        $this->lastScalarStoppedAtComment = false;
        $start = $this->pos;
        $parts = [];
        $currentLineStart = $this->pos;

        while ($this->pos < $this->len) {
            $end = strcspn($this->yaml, "\n\r:#,{}[]-", $this->pos);
            $this->pos += $end;

            if ($this->pos >= $this->len) {
                break;
            }

            $c = $this->yaml[$this->pos];

            if ($c === "\n" || $c === "\r") {
                if ($this->flowDepth > 0) {
                    $savedPos2 = $this->pos;
                    $savedLine2 = $this->line;
                    $this->consumeNewline();

                    $nextIndent2 = strspn($this->yaml, " \t", $this->pos);
                    $nextPos = $this->pos + $nextIndent2;

                    $shouldContinue = false;
                    if ($nextPos < $this->len) {
                        $nextChar = $this->yaml[$nextPos];
                        $nextLineEnd = $nextPos + strcspn($this->yaml, "\n\r", $nextPos);
                        $nextLine = substr($this->yaml, $nextPos, $nextLineEnd - $nextPos);
                        if ($nextChar !== "\n" && $nextChar !== "\r" && $nextChar !== '#' &&
                            $nextChar !== ',' && $nextChar !== ']' && $nextChar !== '}') {
                            $shouldContinue = true;
                            if ($this->inFlowMappingValue && $this->lineContainsKeyIndicator($nextLine)) {
                                $shouldContinue = false;
                            }
                        }
                    }

                    $this->pos = $savedPos2;
                    $this->line = $savedLine2;

                    if (!$shouldContinue) {
                        break;
                    }
                }
                $lineVal = rtrim(substr($this->yaml, $currentLineStart, $this->pos - $currentLineStart));
                $commentPos = strpos($lineVal, ' #');
                if ($commentPos !== false) {
                    $lineVal = rtrim(substr($lineVal, 0, $commentPos));
                }
                if ($lineVal !== '') {
                    $parts[] = $lineVal;
                }

                $savedPos = $this->pos;
                $savedLine = $this->line;
                $this->consumeNewline();

                $blankCount = 0;
                $sawComment = false;
                while ($this->pos < $this->len) {
                    $nextIndent2 = strspn($this->yaml, " \t", $this->pos);
                    $p = $this->pos + $nextIndent2;
                    if ($p >= $this->len) {
                        break;
                    }
                    $nc = $this->yaml[$p];
                    if ($nc === '#') {
                        $sawComment = true;
                        break;
                    }
                    if ($nc === "\n" || $nc === "\r") {
                        $blankCount++;
                        $this->pos = $p;
                        $this->consumeNewline();
                    } else {
                        break;
                    }
                }

                if ($sawComment) {
                    $currentLineStart = $this->pos;
                    $this->lastScalarStoppedAtComment = true;
                    break;
                }

                if ($this->pos >= $this->len) {
                    $currentLineStart = $savedPos;
                    break;
                }

                $nextIndent = strspn($this->yaml, " \t", $this->pos);

                if ($nextIndent >= $contextIndent && $this->flowDepth === 0) {
                    $p = $this->pos + $nextIndent;
                    if ($p < $this->len) {
                        $nc = $this->yaml[$p];
                        if ($contextIndent === 0 && (($nc === '-' && $this->isDocMarker('---')) || ($nc === '.' && $this->isDocMarker('...'))
                                || ($nc === '%' && $this->directiveLineIsFollowedByDocumentStart($p)))) {
                            $this->pos = $savedPos;
                            $this->line = $savedLine;
                            $currentLineStart = $this->pos;
                            break;
                        }
                        if ($nc === '-' && $p + 1 < $this->len && ($this->yaml[$p + 1] === ' ' || $this->yaml[$p + 1] === "\t")) {
                            $this->pos = $savedPos;
                            $this->line = $savedLine;
                            $currentLineStart = $this->pos;
                            break;
                        }
                        if (($nc === '"' || $nc === "'") && $nextIndent === $contextIndent) {
                            $this->pos = $savedPos;
                            $this->line = $savedLine;
                            $currentLineStart = $this->pos;
                            break;
                        }
                        $lineEnd = $p + strcspn($this->yaml, "\n\r", $p);
                        $lineContent = substr($this->yaml, $p, $lineEnd - $p);
                        if ($this->lineContainsKeyIndicator($lineContent)) {
                            $this->pos = $savedPos;
                            $this->line = $savedLine;
                            $currentLineStart = $this->pos;
                            break;
                        }

                        for ($i = 0; $i < $blankCount; $i++) {
                            $parts[] = "\n";
                        }
                        $this->pos = $p;
                        $currentLineStart = $this->pos;
                        continue;
                    }
                } elseif ($this->flowDepth > 0) {
                    $p = $this->pos + $nextIndent;
                    if ($p < $this->len) {
                        $nc = $this->yaml[$p];
                        if ($nc === ',' || $nc === ']' || $nc === '}') {
                            $this->pos = $savedPos;
                            $this->line = $savedLine;
                            $currentLineStart = $this->pos;
                            break;
                        }
                        $this->lastPlainScalarInFlowHadNewline = true;
                        for ($i = 0; $i < $blankCount; $i++) {
                            $parts[] = "\n";
                        }
                        $this->pos = $p;
                        $currentLineStart = $this->pos;
                        continue;
                    }
                }

                $this->pos = $savedPos;
                $this->line = $savedLine;
                $currentLineStart = $this->pos;
                break;
            }

            if ($c === ':') {
                $afterColon = $this->pos + 1 < $this->len ? $this->yaml[$this->pos + 1] : '';
                if ($afterColon === ' ' || $afterColon === "\t" || $afterColon === "\n" || $afterColon === "\r" || $afterColon === '') {
                    break;
                }
                if ($this->flowDepth > 0 && ($afterColon === ',' || $afterColon === '}')) {
                    break;
                }
                $this->pos++;
                continue;
            }

            if ($c === '#') {
                if ($this->pos > $start && $this->yaml[$this->pos - 1] === ' ') {
                    $afterHash = $this->pos + 1 < $this->len ? $this->yaml[$this->pos + 1] : '';
                    if ($afterHash === ' ' || $afterHash === "\t" || $afterHash === "\n" || $afterHash === "\r" || $afterHash === '') {
                        $this->lastScalarStoppedAtComment = true;
                    }
                    break;
                }
                $this->pos++;
                continue;
            }

            if ($c === ',') {
                if ($this->flowDepth > 0) {
                    break;
                }
                $this->pos++;
                continue;
            }

            if ($c === '{' || $c === '}' || $c === '[' || $c === ']') {
                if ($this->flowDepth > 0) {
                    break;
                }
                if ($this->pos === $start) {
                    break;
                }
                $this->pos++;
                continue;
            }

            if ($c === '-') {
                $next = $this->pos + 1 < $this->len ? $this->yaml[$this->pos + 1] : '';
                if (($next === ' ' || $next === "\t") && $this->pos === $currentLineStart) {
                    break;
                }
                if ($this->pos === $currentLineStart && $this->isDocMarker('---')) {
                    break;
                }
                $this->pos++;
                continue;
            }

            $this->pos++;
        }

        if ($parts !== []) {
            $lastPart = rtrim(substr($this->yaml, $currentLineStart, $this->pos - $currentLineStart));
            $commentPos = strpos($lastPart, ' #');
            if ($commentPos !== false) {
                $lastPart = rtrim(substr($lastPart, 0, $commentPos));
            }
            if ($lastPart !== '') {
                $parts[] = $lastPart;
            }
            $result = '';
            foreach ($parts as $i => $p) {
                if ($p === "\n") {
                    $result .= "\n";
                } elseif ($i === 0 || ($result !== '' && str_ends_with($result, "\n"))) {
                    $result .= $p;
                } else {
                    $result .= ' ' . $p;
                }
            }

            return rtrim($result);
        }

        $raw = substr($this->yaml, $start, $this->pos - $start);
        $commentPos = strpos($raw, ' #');
        if ($commentPos !== false) {
            $raw = substr($raw, 0, $commentPos);
        }

        return rtrim($raw);
    }

    /**
     * Read a plain scalar in flow context (stops at , ] }).
     */
    private function readPlainScalarFlow(): string
    {
        $result = '';
        $pendingSpace = false;
        $hadNewline = false;

        while ($this->pos < $this->len) {
            $c = $this->yaml[$this->pos];

            if ($c === ',' || $c === '}' || $c === ']') {
                break;
            }

            if ($c === "\n" || $c === "\r") {
                $savedPos = $this->pos;
                $savedLine = $this->line;
                $this->consumeNewline();

                $nextIndent = strspn($this->yaml, " \t", $this->pos);
                $nextPos = $this->pos + $nextIndent;

                $shouldContinue = false;
                if ($nextPos < $this->len) {
                    $nextChar = $this->yaml[$nextPos];
                    if ($nextChar !== "\n" && $nextChar !== "\r" && $nextChar !== '#' &&
                        $nextChar !== ',' && $nextChar !== ']' && $nextChar !== '}' && $nextChar !== ':') {
                        $shouldContinue = true;
                    }
                }

                $this->pos = $savedPos;
                $this->line = $savedLine;

                if (!$shouldContinue) {
                    $hadNewline = true;
                    break;
                }

                $this->consumeNewline();
                $pendingSpace = true;
                continue;
            }

            if ($c === ':') {
                $next = $this->pos + 1 < $this->len ? $this->yaml[$this->pos + 1] : '';
                if ($next === ' ' || $next === ',' || $next === '}' || $next === ']' || $next === '') {
                    break;
                }
            }

            if ($c === '#' && $result !== '' && str_ends_with($result, ' ')) {
                break;
            }

            if ($pendingSpace && $result !== '' && !str_ends_with($result, ' ')) {
                $result .= ' ';
            }
            $pendingSpace = false;

            $result .= $c;
            $this->pos++;
        }

        $this->lastPlainScalarInFlowHadNewline = $hadNewline;

        return trim($result);
    }

    /**
     * Returns true when the line contains a ':' that acts as a block mapping
     * value indicator (followed by space, tab or end of line).
     */
    private function lineContainsKeyIndicator(string $lineContent): bool
    {
        $offset = 0;
        while (($colonPos = strpos($lineContent, ':', $offset)) !== false) {
            $afterColon = $lineContent[$colonPos + 1] ?? '';
            if ($afterColon === ' ' || $afterColon === "\t" || $afterColon === '') {
                return true;
            }
            $offset = $colonPos + 1;
        }

        return false;
    }

    /**
     * A `%` line only acts as a directive when a document start marker follows.
     * Otherwise it is ordinary plain scalar content.
     */
    private function directiveLineIsFollowedByDocumentStart(int $pos): bool
    {
        while ($pos < $this->len) {
            $lineEnd = $pos + strcspn($this->yaml, "\n\r", $pos);
            $line = rtrim(substr($this->yaml, $pos, $lineEnd - $pos));
            $pos = $lineEnd;
            if ($pos < $this->len && $this->yaml[$pos] === "\r") {
                $pos++;
            }
            if ($pos < $this->len && $this->yaml[$pos] === "\n") {
                $pos++;
            }

            if ($line === '' || $line[0] === '#' || $line[0] === '%') {
                continue;
            }

            return $this->isDocumentBoundaryLine($line);
        }

        return false;
    }

    /**
     * Scalar after a quoted string in block context — check for mapping.
     */
    private function parseSingleQuotedScalarOrMapping(int $indent): mixed
    {
        $hasPrecedingMappingIndicator = $this->lineHasMappingIndicatorBeforeCursor();
        $this->rejectTabQuotedIndent = $hasPrecedingMappingIndicator;
        $startLine = $this->line;
        $startCol = $this->captureMetadata ? $this->columnAt($this->pos) : 0;
        $scalar = $this->readSingleQuotedScalar();
        $this->rejectTabQuotedIndent = false;

        return $this->finishQuotedScalarOrMapping(
            $scalar,
            $indent,
            $startLine,
            $startCol,
            $hasPrecedingMappingIndicator,
            emptyIsNull: false,
        );
    }

    private function parseDoubleQuotedScalarOrMapping(int $indent): mixed
    {
        $hasPrecedingMappingIndicator = $this->lineHasMappingIndicatorBeforeCursor();
        $this->rejectTabQuotedIndent = $hasPrecedingMappingIndicator;
        $startLine = $this->line;
        $startCol = $this->captureMetadata ? $this->columnAt($this->pos) : 0;
        $scalar = $this->readDoubleQuotedScalar($this->flowDepth === 0 && $this->depth > 1 ? $indent : null);
        $this->rejectTabQuotedIndent = false;

        return $this->finishQuotedScalarOrMapping(
            $scalar,
            $indent,
            $startLine,
            $startCol,
            $hasPrecedingMappingIndicator,
            emptyIsNull: $this->version === Version::VERSION_1_1,
        );
    }

    private function finishQuotedScalarOrMapping(
        string $scalar,
        int $indent,
        int $startLine,
        int $startCol,
        bool $hasPrecedingMappingIndicator,
        bool $emptyIsNull,
    ): mixed {
        if ($this->flowDepth === 0 && $this->line !== $startLine && ($this->yaml[$this->pos] ?? '') === ':') {
            throw new ParserException("Multiline quoted scalars are not allowed as implicit mapping keys at line {$startLine}");
        }
        if ($this->flowDepth > 0) {
            $lineBeforeGap = $this->line;
            $this->skipFlowWhitespace();
            if ($this->line !== $lineBeforeGap && ($this->yaml[$this->pos] ?? '') === ':') {
                throw new ParserException("Multiline quoted scalars are not allowed as implicit mapping keys at line {$startLine}");
            }
        } else {
            $this->skipSpaces();
        }

        if ($this->pos < $this->len && $this->yaml[$this->pos] === ':') {
            $afterColon = $this->pos + 1 < $this->len ? $this->yaml[$this->pos + 1] : '';
            if ($afterColon === ' ' || $afterColon === "\t" || $afterColon === "\n" || $afterColon === "\r" || $afterColon === ''
                || ($this->flowDepth > 0 && ($afterColon === ',' || $afterColon === '}' || $afterColon === ']'))
            ) {
                if ($this->flowDepth === 0) {
                    if ($hasPrecedingMappingIndicator) {
                        throw new ParserException("Unexpected ':' after quoted scalar value at line {$this->line}");
                    }
                    if ($this->captureMetadata) {
                        $this->pendingFirstKeyMetadataForMappingBody = new NodeMetadata(line: $startLine, column: $startCol);
                    }
                    $this->pos++;
                    $this->skipSpaces();

                    return $this->parseBlockMappingBody($indent, $scalar);
                }
                $this->pos++;
                $this->skipFlowWhitespace();
                $c2 = $this->yaml[$this->pos] ?? '';
                if ($this->captureMetadata) {
                    $this->lastNodeMetadataTree = null;
                    $valueLine = $this->line;
                    $valueColumn = $this->columnAt($this->pos);
                }
                $value = ($c2 === '}' || $c2 === ',' || $c2 === ']') ? null : $this->parseNode($indent);

                if ($this->captureMetadata) {
                    $this->captureSinglePairMetadata($scalar, $startLine, $startCol, $valueLine, $valueColumn);
                }

                return [$scalar => $value];
            }
        }

        if ($this->pos < $this->len) {
            $c = $this->yaml[$this->pos];
            if ($this->flowDepth > 0) {
                if ($c !== "\n" && $c !== "\r" && $c !== '#' && $c !== ',' && $c !== '}' && $c !== ']' && $c !== ':') {
                    throw new ParserException("Unexpected content after quoted value at line {$this->line}");
                }
            } else {
                if ($c !== "\n" && $c !== "\r" && $c !== '#' && $c !== ':') {
                    throw new ParserException("Unexpected content after quoted value at line {$this->line}");
                }
            }
        }

        if ($scalar === '' && $emptyIsNull) {
            return null;
        }

        return $scalar;
    }

    private function readSingleQuotedScalar(): string
    {
        $startLine = $this->line;
        $this->pos++;
        $result = '';

        while (true) {
            if ($this->pos >= $this->len) {
                throw new LexerException('Unclosed single-quoted scalar.');
            }

            $end = strcspn($this->yaml, "'\n\r", $this->pos);
            $result .= substr($this->yaml, $this->pos, $end);
            $this->pos += $end;

            if ($this->pos >= $this->len) {
                throw new LexerException('Unclosed single-quoted scalar.');
            }

            $c = $this->yaml[$this->pos];

            if ($c === "'") {
                $this->pos++;
                if ($this->pos < $this->len && $this->yaml[$this->pos] === '#') {
                    throw new ParserException("Comment indicator '#' must be preceded by whitespace at line {$this->line}");
                }
                if ($this->pos < $this->len && $this->yaml[$this->pos] === "'") {
                    $result .= "'";
                    $this->pos++;
                    continue;
                }
                break;
            }

            if ($c === "\r" || $c === "\n") {
                $this->consumeNewline();
                $spaceCount = strspn($this->yaml, " \t", $this->pos);
                if ($this->rejectTabQuotedIndent && $spaceCount > 0 && ($this->yaml[$this->pos] ?? '') === "\t") {
                    throw new LexerException("Tab character cannot be used as indentation in a quoted scalar at line {$this->line}");
                }
                $this->pos += $spaceCount;
                if ($this->strictMode && $this->columnAt($this->pos) === 0
                    && ($this->isDocMarker('---') || $this->isDocMarker('...'))
                ) {
                    throw new ParserException("Document marker cannot appear inside a quoted scalar at line {$this->line}");
                }
                if ($this->pos < $this->len && ($this->yaml[$this->pos] === "\n" || $this->yaml[$this->pos] === "\r")) {
                    $result .= "\n";
                } elseif ($result !== '' && !str_ends_with($result, "\n")) {
                    $result .= ' ';
                }
            }
        }

        $this->skipSpaces();
        if ($this->strictMode && $this->flowDepth === 0 && $this->line !== $startLine && ($this->yaml[$this->pos] ?? '') === ':') {
            throw new ParserException("Multiline quoted scalars are not allowed as implicit mapping keys at line {$startLine}");
        }

        return $result;
    }

    private function readDoubleQuotedScalar(?int $contextIndent = null): string
    {
        $startLine = $this->line;
        $this->pos++;
        $raw = '';

        while (true) {
            if ($this->pos >= $this->len) {
                throw new LexerException('Unclosed double-quoted scalar.');
            }

            $end = strcspn($this->yaml, "\"\\\n\r", $this->pos);
            $raw .= substr($this->yaml, $this->pos, $end);
            $this->pos += $end;

            if ($this->pos >= $this->len) {
                throw new LexerException('Unclosed double-quoted scalar.');
            }

            $c = $this->yaml[$this->pos];

            if ($c === '"') {
                $this->pos++;
                if ($this->pos < $this->len && $this->yaml[$this->pos] === '#') {
                    throw new ParserException("Comment indicator '#' must be preceded by whitespace at line {$this->line}");
                }
                break;
            }

            if ($c === '\\') {
                $this->pos++;
                if ($this->pos >= $this->len) {
                    throw new LexerException('Trailing backslash in double-quoted scalar.');
                }
                $esc = $this->yaml[$this->pos];
                $this->pos++;
                if ($esc === "\n" || $esc === "\r") {
                    if ($esc === "\r" && $this->pos < $this->len && $this->yaml[$this->pos] === "\n") {
                        $this->pos++;
                    }
                    $this->line++;
                    $this->pos += strspn($this->yaml, " \t", $this->pos);
                    continue;
                }
                $raw .= $this->resolveEscape($esc, $this->pos - 2);
                continue;
            }

            $raw = rtrim($raw, " \t");
            $this->consumeNewline();
            $spaceCount = strspn($this->yaml, " \t", $this->pos);
            if ($this->rejectTabQuotedIndent && $spaceCount > 0 && ($this->yaml[$this->pos] ?? '') === "\t") {
                throw new LexerException("Tab character cannot be used as indentation in a quoted scalar at line {$this->line}");
            }
            $this->pos += $spaceCount;
            if ($contextIndent !== null && $this->pos < $this->len
                && $this->yaml[$this->pos] !== "\n" && $this->yaml[$this->pos] !== "\r"
                && $this->columnAt($this->pos) <= $contextIndent
            ) {
                throw new ParserException("Wrong indentation in multi-line quoted scalar at line {$this->line}");
            }
            if ($this->strictMode && $this->columnAt($this->pos) === 0
                && ($this->isDocMarker('---') || $this->isDocMarker('...'))
            ) {
                throw new ParserException("Document marker cannot appear inside a quoted scalar at line {$this->line}");
            }

            $emptyLines = 0;
            while ($this->pos < $this->len
                && ($this->yaml[$this->pos] === "\n" || $this->yaml[$this->pos] === "\r")
            ) {
                $this->consumeNewline();
                $this->pos += strspn($this->yaml, " \t", $this->pos);
                $emptyLines++;
            }

            if ($emptyLines > 0) {
                $raw .= str_repeat("\n", $emptyLines);
            } else {
                $raw .= ' ';
            }
        }

        $this->skipSpaces();
        if ($this->strictMode && $this->flowDepth === 0 && $this->line !== $startLine && ($this->yaml[$this->pos] ?? '') === ':') {
            throw new ParserException("Multiline quoted scalars are not allowed as implicit mapping keys at line {$startLine}");
        }

        return $raw;
    }

    private function resolveEscape(string $esc, int $escapeStart): string
    {
        return match ($esc) {
            '\\' => '\\',
            '"' => '"',
            '/' => '/',
            'n' => "\n",
            'r' => "\r",
            't' => "\t",
            "\t" => "\t",
            '0' => "\0",
            'a' => "\x07",
            'b' => "\x08",
            'e' => "\x1B",
            'f' => "\x0C",
            'v' => "\x0B",
            ' ' => ' ',
            'N' => "\xC2\x85",
            '_' => "\xC2\xA0",
            'L' => "\xE2\x80\xA8",
            'P' => "\xE2\x80\xA9",
            'x' => $this->readHexEscape(2, 'x'),
            'u' => $this->readHexEscape(4, 'u'),
            'U' => $this->readHexEscape(8, 'U'),
            default => throw new LexerException(
                "Invalid escape sequence '\\{$esc}' in line {$this->line}, column " . $this->columnAt($escapeStart)
            ),
        };
    }

    private function readHexEscape(int $digits, string $esc): string
    {
        $hex = substr($this->yaml, $this->pos, $digits);
        if (strlen($hex) < $digits || !ctype_xdigit($hex)) {
            throw new LexerException(
                "Invalid escape sequence '\\{$esc}' in line {$this->line}, column " . $this->columnAt($this->pos)
            );
        }
        $this->pos += $digits;
        $cp = hexdec($hex);
        if ($cp > 0x10FFFF || ($cp >= 0xD800 && $cp <= 0xDFFF)) {
            throw new LexerException("Invalid Unicode codepoint U+{$hex}.");
        }
        if ($cp < 0x80) {
            return chr($cp);
        }
        if ($cp < 0x800) {
            return chr(0xC0 | $cp >> 6) . chr(0x80 | $cp & 0x3F);
        }
        if ($cp < 0x10000) {
            return chr(0xE0 | $cp >> 12) . chr(0x80 | ($cp >> 6) & 0x3F) . chr(0x80 | $cp & 0x3F);
        }

        return chr(0xF0 | $cp >> 18) . chr(0x80 | ($cp >> 12) & 0x3F) . chr(0x80 | ($cp >> 6) & 0x3F) . chr(0x80 | $cp & 0x3F);
    }

    private function resolveScalar(string $value): mixed
    {
        return FastScalarResolver::resolve($this->version, $value);
    }
}
