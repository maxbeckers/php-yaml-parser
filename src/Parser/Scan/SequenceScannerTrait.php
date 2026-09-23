<?php

namespace MaxBeckers\YamlParser\Parser\Scan;

use MaxBeckers\YamlParser\Exception\LexerException;
use MaxBeckers\YamlParser\Exception\ParserException;
use MaxBeckers\YamlParser\Metadata\MetadataNode;
use MaxBeckers\YamlParser\Node\NodeMetadata;

/**
 * Internal parser module composed into ScanParser.
 *
 * Block and flow sequence scanning plus flow collection dispatch.
 *
 * This trait is an internal implementation detail of the scanner-parser and
 * is not part of the public API. It operates directly on the scan state
 * declared by ScanParser ($pos, $line, $yaml, ...).
 */
trait SequenceScannerTrait
{
    private function parseBlockSequence(int $indent): array
    {
        $items = [];
        $itemsMeta = [];
        $sequenceOwnMeta = $this->captureMetadata
            ? new NodeMetadata(line: $this->line, column: $indent)
            : null;

        while ($this->pos < $this->len) {
            $c = $this->yaml[$this->pos];

            if ($c !== '-') {
                break;
            }
            $next = $this->pos + 1 < $this->len ? $this->yaml[$this->pos + 1] : '';
            if ($next !== ' ' && $next !== "\t" && $next !== "\n" && $next !== "\r" && $next !== '') {
                break;
            }

            $this->pos++;

            $spacesCount = strspn($this->yaml, ' ', $this->pos);
            $this->pos += $spacesCount;

            if ($this->pos < $this->len && $this->yaml[$this->pos] === "\t") {
                $nextContentPos = $this->pos + 1;
                while ($nextContentPos < $this->len && ($this->yaml[$nextContentPos] === "\t" || $this->yaml[$nextContentPos] === ' ')) {
                    $nextContentPos++;
                }

                if (($this->yaml[$nextContentPos] ?? '') === '-') {
                    $afterDash = $this->yaml[$nextContentPos + 1] ?? '';
                    if ($afterDash === '' || $afterDash === ' ' || $afterDash === "\t" || $afterDash === "\n" || $afterDash === "\r") {
                        throw new LexerException("Tab character cannot be used as separator at line {$this->line}, column {$this->columnAt($this->pos)}");
                    }
                }
            }

            $this->pos += strspn($this->yaml, "\t ", $this->pos);

            if ($this->pos >= $this->len || $this->yaml[$this->pos] === "\n" || $this->yaml[$this->pos] === "\r"
                || $this->yaml[$this->pos] === '#'
            ) {
                $this->skipToEndOfLine();
                $this->consumeNewline();
                $this->skipBlankLinesAndComments();

                if ($this->pos >= $this->len) {
                    $items[] = null;
                    if ($this->captureMetadata) {
                        $itemsMeta[] = MetadataNode::scalar(new NodeMetadata(line: $this->line, column: $this->columnAt($this->pos)));
                    }
                    break;
                }

                $itemIndent = $this->countLineIndent();
                if ($itemIndent <= $indent) {
                    $items[] = null;
                    if ($this->captureMetadata) {
                        $itemsMeta[] = MetadataNode::scalar(new NodeMetadata(line: $this->line, column: $this->columnAt($this->pos)));
                    }
                } else {
                    $this->pos += $itemIndent;
                    $item = $this->parseNode($itemIndent);
                    if (!$this->isAtLineStart()) {
                        $this->skipToEndOfLine();
                    }
                    $items[] = $item;
                    if ($this->captureMetadata) {
                        $itemsMeta[] = $this->lastNodeMetadataTree;
                    }
                }
            } else {
                $c2 = $this->yaml[$this->pos];
                if ($c2 === '|' || $c2 === '>') {
                    $items[] = $this->parseNode($indent);
                    if ($this->captureMetadata) {
                        $itemsMeta[] = $this->lastNodeMetadataTree;
                    }
                    if (!$this->isAtLineStart()) {
                        $this->skipToEndOfLine();
                    }
                } else {
                    $items[] = $this->parseNode($indent + 2);
                    if ($this->captureMetadata) {
                        $itemsMeta[] = $this->lastNodeMetadataTree;
                    }
                    if (!$this->isAtLineStart()) {
                        $this->skipInlineComment();
                        $this->skipToEndOfLine();
                    }
                }
            }

            $this->consumeNewline();
            $this->skipBlankLinesAndComments();

            if ($this->pos >= $this->len) {
                break;
            }

            while ($this->pos < $this->len) {
                $strayIndent = $this->countLineIndent();
                if ($strayIndent <= $indent || $strayIndent >= $indent + 2 || $items === []) {
                    break;
                }
                $lastIdx = array_key_last($items);
                if (!is_string($items[$lastIdx])) {
                    break;
                }
                $strayPos = $this->pos + $strayIndent;
                $strayChar = $this->yaml[$strayPos] ?? '';
                if ($strayChar === '#' || $strayChar === '' || $strayChar === "\n" || $strayChar === "\r") {
                    break;
                }
                $this->pos = $strayPos;
                $lineEnd = $this->pos + strcspn($this->yaml, "\n\r", $this->pos);
                $foldLine = rtrim(substr($this->yaml, $this->pos, $lineEnd - $this->pos));
                $commentPos = strpos($foldLine, ' #');
                if ($commentPos !== false) {
                    $foldLine = rtrim(substr($foldLine, 0, $commentPos));
                }
                if ($foldLine !== '') {
                    $items[$lastIdx] .= ' ' . $foldLine;
                }
                $this->pos = $lineEnd;
                $this->consumeNewline();
                $this->skipBlankLinesAndComments();
            }

            if ($this->pos >= $this->len) {
                break;
            }

            $nextIndent = $this->countLineIndent();
            if ($nextIndent < $indent) {
                break;
            }
            if ($nextIndent > $indent) {
                break;
            }

            $this->pos += $nextIndent;

            if ($this->isDocMarker('---') || $this->isDocMarker('...')) {
                $this->pos -= $nextIndent;
                break;
            }
        }

        if ($this->captureMetadata) {
            $this->lastNodeMetadataTree = MetadataNode::sequence($itemsMeta, $sequenceOwnMeta);
        }

        return $items;
    }

    private function parseFlowSequence(): array
    {
        $flowStartLine = $this->line;
        $flowStartCol = $this->columnAt($this->pos);
        $itemsMeta = [];
        $this->pos++;
        $this->flowDepth++;
        $this->skipFlowWhitespace();

        $items = [];
        $foundClosing = false;
        $isFirstItem = true;
        $hasItem = false;
        $itemAfterComma = false;

        while ($this->pos < $this->len) {
            $c = $this->yaml[$this->pos];

            if ($c === ']') {
                if (!$itemAfterComma && !$isFirstItem) {
                    throw new ParserException("Unexpected comma before closing bracket in flow sequence at line {$this->line}");
                }
                $this->pos++;
                $this->flowDepth--;
                $foundClosing = true;
                break;
            }

            if ($c === ',') {
                if (!$itemAfterComma && !$isFirstItem) {
                    throw new ParserException("Unexpected consecutive commas in flow sequence at line {$this->line}");
                }
                if (!$hasItem && $isFirstItem) {
                    throw new ParserException("Unexpected comma at beginning of flow sequence at line {$this->line}");
                }
                $this->pos++;

                if ($this->pos < $this->len && $this->yaml[$this->pos] === '#') {
                    throw new ParserException("Comment indicator '#' must be preceded by whitespace in flow context at line {$this->line}");
                }

                $skippedNewline = $this->skipFlowWhitespace();

                $nextC = $this->yaml[$this->pos] ?? '';
                if ($skippedNewline && $nextC !== ']' && $nextC !== '}' && $nextC !== ',' && $nextC !== '"' && $nextC !== "'" && $nextC !== '[' && $nextC !== '{' && $nextC !== '&' && $nextC !== '*' && $nextC !== '!' && $nextC !== '?') {
                    $itemCol = $this->columnAt($this->pos);
                    if ($itemCol === 0 && $flowStartCol > 0) {
                        throw new ParserException("Wrong indented flow sequence at line {$this->line}");
                    }
                }
            }

            $itemIndent = $this->countLineIndent();
            $posBefore = $this->pos;

            if ($c === '-') {
                $next = $this->pos + 1 < $this->len ? $this->yaml[$this->pos + 1] : '';
                if ($next === ' ' || $next === "\t" || $next === "\n" || $next === "\r" || $next === '' || $next === ']' || $next === ',') {
                    throw new ParserException("Dash cannot be used as a value in flow context at line {$this->line}");
                }
            }

            $item = $this->parseNode($itemIndent);

            if ($this->lastPlainScalarInFlowHadNewline) {
                if (is_array($item) && !array_is_list($item) && count($item) === 1 && !$this->lastKeyWasExplicit) {
                    throw new ParserException("Expected ',' or ']' in flow sequence at line {$this->line}, column 1");
                }
            }
            $this->lastPlainScalarInFlowHadNewline = false;
            $this->lastKeyWasExplicit = false;

            $items[] = $item;
            if ($this->captureMetadata) {
                $itemsMeta[] = $this->lastNodeMetadataTree;
            }
            $hasItem = true;
            $isFirstItem = false;
            $itemAfterComma = true;

            if ($this->pos === $posBefore) {
                $this->pos++;
            }
            $skippedNewlineAfterItem = $this->skipFlowWhitespace();

            if ($this->pos < $this->len && $this->yaml[$this->pos] === ',') {
                $this->pos++;

                if ($this->pos < $this->len && $this->yaml[$this->pos] === '#') {
                    throw new ParserException("Comment indicator '#' must be preceded by whitespace in flow context at line {$this->line}");
                }

                $skippedNewline = $this->skipFlowWhitespace();

                if ($skippedNewline) {
                    $nextC = $this->yaml[$this->pos] ?? '';
                    if ($nextC !== ']' && $nextC !== '}' && $nextC !== ',' && $nextC !== '"' && $nextC !== "'" && $nextC !== '[' && $nextC !== '{' && $nextC !== '&' && $nextC !== '*' && $nextC !== '!' && $nextC !== '?') {
                        $itemCol = $this->columnAt($this->pos);
                        if ($itemCol === 0 && $flowStartCol > 0) {
                            throw new ParserException("Wrong indented flow sequence at line {$this->line}");
                        }
                    }
                }
            } elseif ($skippedNewlineAfterItem && $this->pos < $this->len && $this->yaml[$this->pos] === ':') {
                throw new ParserException("Expected ',' or ']' in flow sequence at line {$this->line}");
            } elseif ($this->pos < $this->len && $this->yaml[$this->pos] !== ']' && $this->yaml[$this->pos] !== '#' && $this->yaml[$this->pos] !== ':' && $this->yaml[$this->pos] !== '?') {
                throw new ParserException("Expected ',' or ']' in flow sequence at line {$this->line}");
            }
        }

        if (!$foundClosing) {
            throw new ParserException("Flow sequence without closing bracket at line {$this->line}");
        }

        $this->skipSpaces();

        if ($this->flowDepth === 0 && $this->pos < $this->len && $this->yaml[$this->pos] === ']') {
            throw new ParserException("Unexpected extra closing bracket ']' at line {$this->line}");
        }

        $this->validateFlowCollectionTrailingContent();

        if ($this->captureMetadata) {
            $this->lastNodeMetadataTree = MetadataNode::sequence(
                $itemsMeta,
                new NodeMetadata(line: $flowStartLine, column: $flowStartCol),
            );
        }

        return $items;
    }

    /**
     * Parse a flow collection and, when it is used as a mapping key, the mapping
     * it introduces (`[a, b]: value` in block context, `{a: b}: c` in flow context).
     */
    private function parseFlowCollectionNode(int $indent): mixed
    {
        $suppressPairKey = $this->suppressFlowPairKey;
        $this->suppressFlowPairKey = false;

        $outerFlowDepth = $this->flowDepth;
        $startLine = $this->line;
        $startCol = $this->columnAt($this->pos);
        $node = $this->yaml[$this->pos] === '[' ? $this->parseFlowSequence() : $this->parseFlowMapping();

        if ($suppressPairKey) {
            return $node;
        }

        $this->skipSpaces();
        if (($this->yaml[$this->pos] ?? '') !== ':') {
            return $node;
        }

        $afterColon = $this->yaml[$this->pos + 1] ?? '';
        if ($outerFlowDepth === 0) {
            if ($afterColon !== ' ' && $afterColon !== "\t" && $afterColon !== "\n" && $afterColon !== "\r" && $afterColon !== '') {
                return $node;
            }

            if ($this->line !== $startLine) {
                throw new ParserException("Flow collection used as an implicit mapping key cannot span multiple lines at line {$startLine}");
            }

            if ($this->captureMetadata) {
                $this->pendingFirstKeyMetadataForMappingBody = new NodeMetadata(line: $startLine, column: $startCol);
            }
            $this->pos++;
            $this->skipSpaces();

            return $this->parseBlockMappingBody($indent, $this->flowKeyToString($node));
        }

        $this->pos++;
        $this->skipFlowWhitespace();
        $c = $this->yaml[$this->pos] ?? '';
        if ($this->captureMetadata) {
            $this->lastNodeMetadataTree = null;
            $valueLine = $this->line;
            $valueColumn = $this->columnAt($this->pos);
        }
        $value = ($c === '' || $c === '}' || $c === ',' || $c === ']') ? null : $this->parseNode($indent);

        if ($this->captureMetadata) {
            $this->captureSinglePairMetadata(
                $this->flowKeyToString($node),
                $startLine,
                $startCol,
                $valueLine,
                $valueColumn,
            );
        }

        return [$this->flowKeyToString($node) => $value];
    }
}
