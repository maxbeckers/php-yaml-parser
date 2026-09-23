<?php

namespace MaxBeckers\YamlParser\Parser\Scan;

use MaxBeckers\YamlParser\Exception\LexerException;
use MaxBeckers\YamlParser\Exception\ParserException;

/**
 * Internal parser module composed into ScanParser.
 *
 * Literal and folded block scalar scanning, chomping and line folding.
 *
 * This trait is an internal implementation detail of the scanner-parser and
 * is not part of the public API. It operates directly on the scan state
 * declared by ScanParser ($pos, $line, $yaml, ...).
 */
trait BlockScalarScannerTrait
{
    private function parseTopLevelBlockScalar(): string
    {
        $c = $this->yaml[$this->pos] ?? '';

        return $c === '|'
            ? $this->parseLiteralBlock(-1)
            : $this->parseFoldedBlock(-1);
    }

    private function parseLiteralBlock(int $contextIndent): string
    {
        $this->pos++;
        if ($this->strictMode && $this->pos < $this->len && !in_array($this->yaml[$this->pos], [' ', "\t", "\n", "\r", '+', '-', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '#'], true)) {
            throw new LexerException("Invalid text after literal block scalar indicator at line {$this->line}");
        }
        if ($this->pos < $this->len && $this->yaml[$this->pos] === '#') {
            throw new ParserException("Comment indicator '#' must be preceded by whitespace at line {$this->line}");
        }
        [$chompingIndicator, $indentIndicator] = $this->readBlockScalarHeader();
        $this->validateBlockScalarHeaderTail('literal');

        $this->skipToEndOfLine();
        $this->consumeNewline();

        return $this->collectBlockScalarLines($contextIndent, $indentIndicator, $chompingIndicator, false);
    }

    private function parseFoldedBlock(int $contextIndent): string
    {
        $this->pos++;
        if ($this->strictMode && $this->pos < $this->len && !in_array($this->yaml[$this->pos], [' ', "\t", "\n", "\r", '+', '-', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '#'], true)) {
            throw new LexerException("Invalid text after folded block scalar indicator at line {$this->line}");
        }
        if ($this->pos < $this->len && $this->yaml[$this->pos] === '#') {
            throw new ParserException("Comment indicator '#' must be preceded by whitespace at line {$this->line}");
        }
        [$chompingIndicator, $indentIndicator] = $this->readBlockScalarHeader();
        $this->validateBlockScalarHeaderTail('folded');

        $this->skipToEndOfLine();
        $this->consumeNewline();

        return $this->collectBlockScalarLines($contextIndent, $indentIndicator, $chompingIndicator, true);
    }

    /**
     * After the block scalar indicator and its optional chomping/indentation
     * indicators, only whitespace and/or a trailing comment may remain on the
     * header line — no other content (e.g. a plain scalar) is allowed there.
     */
    private function validateBlockScalarHeaderTail(string $kind): void
    {
        if (!$this->strictMode) {
            return;
        }
        $lineEnd = $this->pos + strcspn($this->yaml, "\n\r", $this->pos);
        $tail = substr($this->yaml, $this->pos, $lineEnd - $this->pos);
        $trimmed = ltrim($tail, " \t");
        if ($trimmed !== '' && $trimmed[0] !== '#') {
            throw new ParserException("Invalid content after {$kind} block scalar header at line {$this->line}");
        }
    }

    /**
     * Read the block scalar header (chomping + indentation indicators).
     *
     * @return array{string|null, int|null}
     */
    private function readBlockScalarHeader(): array
    {
        $chomp = null;
        $indent = null;

        for ($i = 0; $i < 2 && $this->pos < $this->len; $i++) {
            $c = $this->yaml[$this->pos];
            if ($c === '-' || $c === '+') {
                $chomp = $c;
                $this->pos++;
            } elseif ($c === '0') {
                throw new LexerException('Block scalar indentation indicator cannot be 0.');
            } elseif ($c >= '1' && $c <= '9') {
                $indent = (int) $c;
                $this->pos++;

                $next = $this->yaml[$this->pos] ?? '';
                if ($next >= '0' && $next <= '9') {
                    throw new LexerException('Block scalar indentation indicator must be a single digit from 1 to 9.');
                }
            } else {
                break;
            }
        }

        return [$chomp, $indent];
    }

    private function collectBlockScalarLines(
        int $contextIndent,
        ?int $explicitIndent,
        ?string $chomp,
        bool $folded,
    ): string {
        $blockIndent = $explicitIndent;
        $lines = [];
        $kind = $folded ? 'Folded' : 'Literal';
        $leadingEmptyIndent = 0;
        $firstContentLine = null;

        while ($this->pos < $this->len) {
            $lineIndentCount = strspn($this->yaml, ' ', $this->pos);
            $lineStart = $this->pos + $lineIndentCount;

            if ($this->yaml[$this->pos] === "\t") {
                throw new LexerException("{$kind} block scalar cannot use tabs for indentation at line {$this->line}");
            }

            if ($lineStart >= $this->len || $this->yaml[$lineStart] === "\n" || $this->yaml[$lineStart] === "\r") {
                if ($blockIndent !== null) {
                    $emptyContent = $lineIndentCount > $blockIndent ? str_repeat(' ', $lineIndentCount - $blockIndent) : '';
                    $lines[] = $emptyContent;
                } else {
                    $leadingEmptyIndent = max($leadingEmptyIndent, $lineIndentCount);
                    $lines[] = '';
                }
                $this->pos = $lineStart;
                $this->consumeNewline();
                continue;
            }

            if ($firstContentLine === null) {
                $firstContentLine = $this->line;
            }

            if ($blockIndent === null) {
                if ($contextIndent >= 0 && $lineIndentCount <= $contextIndent) {
                    break;
                }
                $blockIndent = $lineIndentCount;

                if ($leadingEmptyIndent > $blockIndent) {
                    throw new LexerException("{$kind} block scalar indentation less than the defined indentation in line {$firstContentLine}, column 0");
                }
            }

            if ($lineIndentCount < $blockIndent) {
                if ($contextIndent >= 0 && $lineIndentCount > $contextIndent && $lineIndentCount > 0 && $this->yaml[$lineStart] !== '#') {
                    throw new LexerException("{$kind} block scalar indentation less than the defined indentation in line {$firstContentLine}, column 0");
                }
                break;
            }

            if ($contextIndent >= 0 && $lineIndentCount <= $contextIndent) {
                $lineEnd = $lineStart + strcspn($this->yaml, "\n\r", $lineStart);
                $lineContent = substr($this->yaml, $lineStart, $lineEnd - $lineStart);
                if ($this->isDocumentBoundaryLine($lineContent)) {
                    break;
                }
                if (preg_match('/(?:^|[^:]):\s|^-\s/', $lineContent)) {
                    break;
                }
                if ($this->yaml[$lineStart] === '#') {
                    $this->pos = $lineEnd;
                    $this->consumeNewline();
                    continue;
                }
                break;
            }

            if ($contextIndent < 0 && $lineIndentCount === 0) {
                $lineEnd = $lineStart + strcspn($this->yaml, "\n\r", $lineStart);
                $lineContent = substr($this->yaml, $lineStart, $lineEnd - $lineStart);
                if ($this->isDocumentBoundaryLine($lineContent)) {
                    break;
                }
            }

            $contentStart = $this->pos + $blockIndent;
            $contentEnd = $contentStart + strcspn($this->yaml, "\n\r", $contentStart);
            $lineContent = substr($this->yaml, $contentStart, $contentEnd - $contentStart);

            $lines[] = $lineContent;
            $this->pos = $contentEnd;
            $this->consumeNewline();
        }

        return $this->applyChomping($this->joinBlockLines($lines, $folded), $chomp);
    }

    private function joinBlockLines(array $lines, bool $folded): string
    {
        if (!$folded) {
            return implode("\n", $lines);
        }

        $result = '';
        $pendingNewlines = 0;
        $prevWasRegular = false;

        foreach ($lines as $line) {
            $isBlank = ($line === '');
            $isIndented = !$isBlank && ($line[0] === ' ' || $line[0] === "\t");
            $isRegular = !$isBlank && !$isIndented;

            if ($isBlank) {
                $pendingNewlines++;
                continue;
            }

            if ($result === '') {
                if ($pendingNewlines > 0) {
                    $result .= str_repeat("\n", $pendingNewlines);
                }
                $result .= $line;
            } elseif ($isRegular && $prevWasRegular && $pendingNewlines === 0) {
                $result .= ' ' . $line;
            } elseif ($isRegular && $prevWasRegular && $pendingNewlines > 0) {
                $result .= str_repeat("\n", $pendingNewlines) . $line;
            } else {
                $result .= str_repeat("\n", $pendingNewlines + 1) . $line;
            }

            $pendingNewlines = 0;
            $prevWasRegular = $isRegular;
        }

        if ($pendingNewlines > 0) {
            $result .= str_repeat("\n", $pendingNewlines);
        }

        return $result;
    }

    private function applyChomping(string $scalar, ?string $chomp): string
    {
        if (rtrim($scalar, "\n") === '') {
            return $chomp === '+' ? "\n" : '';
        }

        if ($chomp === '-') {
            return rtrim($scalar, "\n");
        }
        if ($chomp === '+') {
            return $scalar . "\n";
        }

        return rtrim($scalar, "\n") . "\n";
    }

    private function isDocumentBoundaryLine(string $line): bool
    {
        if (!str_starts_with($line, '---') && !str_starts_with($line, '...')) {
            return false;
        }
        $c = $line[3] ?? '';

        return $c === '' || $c === ' ' || $c === "\t" || $c === '#';
    }
}
