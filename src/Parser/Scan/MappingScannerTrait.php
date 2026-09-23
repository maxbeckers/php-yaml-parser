<?php

namespace MaxBeckers\YamlParser\Parser\Scan;

use MaxBeckers\YamlParser\Exception\LexerException;
use MaxBeckers\YamlParser\Exception\ParserException;
use MaxBeckers\YamlParser\Exception\ResolverException;
use MaxBeckers\YamlParser\Metadata\MetadataNode;
use MaxBeckers\YamlParser\Node\NodeMetadata;

/**
 * Internal parser module composed into ScanParser.
 *
 * Block and flow mapping scanning, flow key normalisation and merge-key handling.
 *
 * This trait is an internal implementation detail of the scanner-parser and
 * is not part of the public API. It operates directly on the scan state
 * declared by ScanParser ($pos, $line, $yaml, ...).
 */
trait MappingScannerTrait
{
    /**
     * Parse a block mapping, given the first key already consumed and ': ' skipped.
     */
    private function parseBlockMappingBody(int $indent, string $firstKey): array
    {
        $mapping = [];
        $mappingMeta = [];
        $keyMeta = null;
        $mappingOwnMeta = null;

        if ($this->captureMetadata) {
            $keyMeta = $this->pendingFirstKeyMetadataForMappingBody ?? new NodeMetadata(line: $this->line, column: $indent);
            $this->pendingFirstKeyMetadataForMappingBody = null;
            $mappingOwnMeta = $keyMeta;
        }

        $key = $firstKey;

        while (true) {
            if ($this->captureMetadata) {
                $this->lastNodeMetadataTree = null;
                $valueLine = $this->line;
                $valueColumn = $this->columnAt($this->pos);
            }

            if ($this->pos >= $this->len) {
                $value = null;
            } else {
                $c = $this->yaml[$this->pos];
                if ($c === "\n" || $c === "\r" || $c === '#' || $c === '') {
                    $this->skipToEndOfLine();
                    $this->consumeNewline();
                    $this->skipBlankLinesAndComments();

                    if ($this->pos >= $this->len) {
                        $value = null;
                    } else {
                        $nextIndent = $this->countLineIndent();
                        $nextChar = $this->yaml[$this->pos + $nextIndent] ?? '';

                        if ($nextIndent <= $indent && $nextChar !== '-') {
                            $value = null;
                        } else {
                            $this->pos += $nextIndent;
                            $value = $nextChar === '-' ? $this->parseBlockSequence($nextIndent) : $this->parseNode($nextIndent);
                            if ($nextChar === '-' && !$this->isAtLineStart()) {
                                $this->skipInlineComment();
                                if (!$this->isAtLineStart() && $this->pos < $this->len) {
                                    throw new ParserException("Unexpected content after block sequence at line {$this->line}");
                                }
                            }
                            if (!$this->isAtLineStart()) {
                                $this->skipToEndOfLine();
                            }
                        }
                    }
                } else {
                    $valueStart = $this->pos;

                    $c = $this->yaml[$valueStart] ?? '';
                    if ($c === '-') {
                        $next = $this->yaml[$valueStart + 1] ?? '';
                        $lineEnd = strpos($this->yaml, "\n", max(0, $valueStart - 100));
                        if ($lineEnd === false || $lineEnd > $valueStart) {
                            if ($next === ' ' || $next === "\t" || $next === ',' || $next === '#' || $next === '') {
                                throw new ParserException("Cannot start value with dash in block mapping (use next line) at line {$this->line}");
                            }
                        }
                    }

                    $value = $this->parseNode($indent);
                    if ($this->strictMode && is_array($value)) {
                        $first = $this->yaml[$valueStart] ?? '';
                        if (!in_array($first, ['[', '{', '&', '!', '\'', '"', '|', '>', '*', '-', '?'], true)) {
                            throw new ParserException('Malformed nested mapping in scalar value.');
                        }
                    }
                    if (!$this->isAtLineStart()) {
                        $this->skipInlineComment();
                        $this->skipToEndOfLine();
                    }
                }
            }

            if ($this->captureMetadata) {
                $this->captureMappingEntryMetadata($mappingMeta, $key, $keyMeta, $valueLine, $valueColumn);
            }

            if ($key === '<<') {
                $this->mergeIntoMapping($mapping, $value);
            } else {
                $mapping[$key] = $value;
            }

            $lineBefore2 = $this->line;
            $this->consumeNewline();
            $this->skipBlankLinesAndComments();

            if ($this->pos >= $this->len) {
                break;
            }

            $nextIndent = $this->countLineIndent();

            if ($nextIndent < $indent) {
                break;
            }

            if ($nextIndent > $indent) {
                $savedPos = $this->pos;
                $this->pos += $nextIndent;
                $nextChar = $this->yaml[$this->pos] ?? '';

                $currentVal = $mapping[$key] ?? null;
                $hadBlankLineBeforeContinuation = ($this->line - $lineBefore2) > 1;
                nextContinuation:
                if (($currentVal === null || is_string($currentVal))
                    && !in_array($nextChar, ['-', '|', '>', '[', '{', '&', '!', '*', '?', ':', '"', '\''], true)
                ) {
                    if (is_string($currentVal) && $currentVal !== '' && $this->lastScalarStoppedAtComment && !$hadBlankLineBeforeContinuation) {
                        throw new ParserException("Unexpected content after comment inside plain scalar value at line {$this->line}");
                    }
                    if (is_string($currentVal) && $currentVal !== '') {
                        $lineEndCheck = $this->pos + strcspn($this->yaml, "\n\r", $this->pos);
                        $lineContentCheck = substr($this->yaml, $this->pos, $lineEndCheck - $this->pos);
                        if ($this->lineContainsKeyIndicator($lineContentCheck)) {
                            throw new ParserException("Unexpected mapping entry inside plain scalar value at line {$this->line}");
                        }
                    }
                    $continuation = $this->parsePlainScalarOrCollection($nextIndent);
                    if (is_string($continuation) && $continuation !== '') {
                        if ($currentVal === null) {
                            $mapping[$key] = $continuation;
                        } else {
                            $mapping[$key] .= $hadBlankLineBeforeContinuation ? "\n" : ' ';
                            $mapping[$key] .= $continuation;
                        }
                    }

                    if (!$this->isAtLineStart()) {
                        $this->skipToEndOfLine();
                    }
                    $lineBefore = $this->line;
                    $this->consumeNewline();
                    $this->skipBlankLinesAndComments();
                    $hadBlankLineBeforeContinuation = ($this->line - $lineBefore) > 1;

                    if ($this->pos >= $this->len) {
                        break;
                    }

                    $nextIndent3 = $this->countLineIndent();
                    if ($nextIndent3 < $indent) {
                        break;
                    }
                    if ($nextIndent3 > $indent) {
                        $savedPos = $this->pos;
                        $this->pos += $nextIndent3;
                        $nextChar = $this->yaml[$this->pos] ?? '';
                        $currentVal = $mapping[$key] ?? null;
                        $nextIndent = $nextIndent3;
                        goto nextContinuation;
                    }
                    $nextIndent = $nextIndent3;
                } else {
                    $this->pos = $savedPos;
                    break;
                }
            }

            $savedPos = $this->pos;
            $this->pos += $nextIndent;

            $c = $this->yaml[$this->pos] ?? '';

            if ($c === "\t") {
                throw new LexerException("Tab character found in block indentation at line {$this->line}");
            }

            if ($c === '-' && $this->isDocMarker('---')) {
                $this->pos = $savedPos;
                break;
            }
            if ($c === '.' && $this->isDocMarker('...')) {
                $this->pos = $savedPos;
                break;
            }

            $anchorName = null;
            if ($c === '&') {
                $this->pos++;
                $end = strcspn($this->yaml, " \t\r\n,{}[]", $this->pos);
                if ($end === 0) {
                    throw new ParserException("Invalid anchor: empty anchor name at line {$this->line}");
                }
                $anchorName = substr($this->yaml, $this->pos, $end);
                $this->pos += $end;
                $this->skipSpaces();
                $c = $this->yaml[$this->pos] ?? '';
            }

            if ($c === '-' && ($this->pos + 1 >= $this->len || $this->yaml[$this->pos + 1] === ' ' || $this->yaml[$this->pos + 1] === "\t")) {
                $this->pos = $savedPos;
                break;
            }

            if ($anchorName !== null && $c === '*') {
                throw new ParserException("A node cannot be both anchored and an alias at line {$this->line}");
            }

            if ($this->captureMetadata) {
                $keyMeta = new NodeMetadata(line: $this->line, column: $this->columnAt($this->pos));
            }

            if ($c === "'") {
                $keyStartLine = $this->line;
                $key = $this->readSingleQuotedScalar();
                if ($this->line !== $keyStartLine) {
                    throw new ParserException("Multiline quoted scalars are not allowed as implicit mapping keys at line {$keyStartLine}");
                }
                $this->skipSpaces();
            } elseif ($c === '"') {
                $keyStartLine = $this->line;
                $key = $this->readDoubleQuotedScalar();
                if ($this->line !== $keyStartLine) {
                    throw new ParserException("Multiline quoted scalars are not allowed as implicit mapping keys at line {$keyStartLine}");
                }
                $this->skipSpaces();
            } elseif ($c === '[' || $c === '{') {
                $key = $this->flowKeyToString($c === '[' ? $this->parseFlowSequence() : $this->parseFlowMapping());
                $this->skipSpaces();
            } elseif ($c === '?') {
                $next = $this->pos + 1 < $this->len ? $this->yaml[$this->pos + 1] : '';
                if ($next === ' ' || $next === "\t" || $next === "\n" || $next === "\r" || $next === '') {
                    $explicitPairs = $this->parseExplicitKeyBlock($indent);
                    $explicitMetaTree = $this->captureMetadata ? $this->lastNodeMetadataTree : null;
                    foreach ($explicitPairs as $ek => $ev) {
                        if ($ek === '<<') {
                            $this->mergeIntoMapping($mapping, $ev);
                        } else {
                            $mapping[$ek] = $ev;
                            if ($this->captureMetadata) {
                                $mappingMeta[$ek] = ($explicitMetaTree !== null && $explicitMetaTree->isMapping())
                                    ? [
                                        'key' => $explicitMetaTree->getMappingKeyMetadata($ek),
                                        'value' => $explicitMetaTree->getMappingValueNode($ek) ?? MetadataNode::scalar(null),
                                    ]
                                    : ['key' => null, 'value' => MetadataNode::scalar(null)];
                            }
                        }
                    }
                    $this->consumeNewline();
                    $this->skipBlankLinesAndComments();
                    if ($this->pos >= $this->len) {
                        break;
                    }
                    $nextIndent = $this->countLineIndent();
                    if ($nextIndent < $indent) {
                        break;
                    }
                    $this->pos += $nextIndent;
                    continue;
                }
                $key = $this->readPlainScalar($indent);
                $this->skipSpaces();
            } elseif ($c === '' || $c === '#') {
                $this->pos = $savedPos;
                break;
            } elseif ($c === ':') {
                $next = $this->pos + 1 < $this->len ? $this->yaml[$this->pos + 1] : '';
                if ($next === ' ' || $next === "\t" || $next === "\n" || $next === "\r" || $next === '') {
                    $key = 'null';
                } else {
                    $key = $this->readPlainScalar($indent);
                    $this->skipSpaces();
                }
            } else {
                $key = $this->readPlainScalar($indent);
                $this->skipSpaces();
            }

            if ($this->pos >= $this->len || $this->yaml[$this->pos] !== ':') {
                $this->pos = $savedPos;
                break;
            }

            $afterColon = $this->pos + 1 < $this->len ? $this->yaml[$this->pos + 1] : '';
            if ($afterColon !== ' ' && $afterColon !== "\t" && $afterColon !== "\n" && $afterColon !== "\r" && $afterColon !== '') {
                $this->pos = $savedPos;
                break;
            }

            if ($anchorName !== null) {
                $this->anchors[$anchorName] = $this->deepCopy($key);
            }

            $this->pos++;

            $this->pos += strspn($this->yaml, ' ', $this->pos);

            if ($this->pos < $this->len && $this->yaml[$this->pos] === "\t") {
                $nextContentPos = $this->pos + 1;
                while ($nextContentPos < $this->len && ($this->yaml[$nextContentPos] === "\t" || $this->yaml[$nextContentPos] === ' ')) {
                    $nextContentPos++;
                }

                $nextContent = $this->yaml[$nextContentPos] ?? '';
                if ($nextContent !== '' && $nextContent !== "\n" && $nextContent !== "\r" && $nextContent !== '#' && $nextContent !== '|' && $nextContent !== '>') {
                    throw new LexerException("Tab character cannot be used as separator at line {$this->line}, column {$this->columnAt($this->pos)}");
                }
            }

            $this->pos += strspn($this->yaml, "\t ", $this->pos);
        }

        if ($this->captureMetadata) {
            $this->lastNodeMetadataTree = MetadataNode::mapping($mappingMeta, $mappingOwnMeta);
        }

        return $mapping;
    }

    /**
     * Parse an explicit block mapping starting with `? key\n: value`.
     * Can produce a multi-entry mapping if more `?` or plain keys follow.
     */
    private function parseExplicitKeyBlock(int $indent): array
    {
        $mapping = [];
        $mappingMeta = [];

        while ($this->pos < $this->len) {
            $c = $this->yaml[$this->pos];

            if ($c === ':') {
                $next = $this->pos + 1 < $this->len ? $this->yaml[$this->pos + 1] : '';
                if ($next !== ' ' && $next !== "\t" && $next !== "\n" && $next !== "\r" && $next !== '') {
                    break;
                }
                $nullKeyMeta = $this->captureMetadata
                    ? new NodeMetadata(line: $this->line, column: $this->columnAt($this->pos))
                    : null;
                if ($this->captureMetadata) {
                    $this->lastNodeMetadataTree = null;
                }
                $this->pos++;
                $this->skipSpaces();
                if ($this->captureMetadata) {
                    $valueLine = $this->line;
                    $valueColumn = $this->columnAt($this->pos);
                }
                $c2 = $this->yaml[$this->pos] ?? '';
                if ($this->pos >= $this->len || $c2 === "\n" || $c2 === "\r" || $c2 === '#') {
                    $this->skipToEndOfLine();
                    $this->consumeNewline();
                    $this->skipBlankLinesAndComments();
                    $valIndent = $this->countLineIndent();
                    if ($valIndent > $indent && $this->pos < $this->len) {
                        $this->pos += $valIndent;
                        $value = $this->parseNode($valIndent);
                    } else {
                        $value = null;
                    }
                } else {
                    $lineStart = strrpos(substr($this->yaml, 0, $this->pos), "\n");
                    $valueCol = ($lineStart === false) ? $this->pos : ($this->pos - $lineStart - 1);
                    if ($valueCol < 0) {
                        $valueCol = 0;
                    }
                    $value = $this->parseNode($valueCol);
                    if (!$this->isAtLineStart()) {
                        $this->skipInlineComment();
                        $this->skipToEndOfLine();
                    }
                }

                if ($this->captureMetadata) {
                    $this->captureMappingEntryMetadata($mappingMeta, 'null', $nullKeyMeta, $valueLine, $valueColumn);
                }

                $mapping['null'] = $value;

                $this->consumeNewline();
                $this->skipBlankLinesAndComments();

                if ($this->pos >= $this->len) {
                    break;
                }

                $nextIndentAfterNullKey = $this->countLineIndent();
                if ($nextIndentAfterNullKey < $indent) {
                    break;
                }

                $this->pos += $nextIndentAfterNullKey;
                continue;
            }

            if ($c === '?') {
                $next = $this->pos + 1 < $this->len ? $this->yaml[$this->pos + 1] : '';
                if ($next !== ' ' && $next !== "\t" && $next !== "\n" && $next !== "\r" && $next !== '') {
                    break;
                }
                $explicitKeyMeta = $this->captureMetadata
                    ? new NodeMetadata(line: $this->line, column: $this->columnAt($this->pos))
                    : null;
                $this->pos++;

                $this->skipMappingIndicatorWhitespace(allowBlockScalar: false);

                $keyAnchor = null;
                if (($this->yaml[$this->pos] ?? '') === '&') {
                    $this->pos++;
                    $anchorEnd = strcspn($this->yaml, " \t\r\n,{}[]", $this->pos);
                    if ($anchorEnd === 0) {
                        throw new ParserException("Invalid anchor: empty anchor name at line {$this->line}");
                    }
                    $keyAnchor = substr($this->yaml, $this->pos, $anchorEnd);
                    $this->pos += $anchorEnd;
                    $this->skipSpaces();
                }

                $keyChar = $this->yaml[$this->pos] ?? '';
                $keyNext = $this->yaml[$this->pos + 1] ?? '';

                if ($keyChar === '[' || $keyChar === '{') {
                    $rawKey = $this->flowKeyToString($keyChar === '[' ? $this->parseFlowSequence() : $this->parseFlowMapping());
                } elseif ($keyChar === '|' || $keyChar === '>') {
                    $rawKey = $keyChar === '|' ? $this->parseLiteralBlock($indent) : $this->parseFoldedBlock($indent);
                } elseif ($keyChar === '-' && ($keyNext === ' ' || $keyNext === "\t" || $keyNext === "\n" || $keyNext === "\r" || $keyNext === '')) {
                    $rawKey = $this->flowKeyToString($this->parseBlockSequence($this->columnAt($this->pos)));
                } elseif ($keyChar === '' || $keyChar === "\n" || $keyChar === "\r" || $keyChar === '#') {
                    $this->skipToEndOfLine();
                    $this->consumeNewline();
                    $this->skipBlankLinesAndComments();
                    $keyLineIndent = $this->countLineIndent();
                    if ($keyLineIndent === $indent && $this->pos + $keyLineIndent < $this->len
                        && $this->yaml[$this->pos + $keyLineIndent] === '-'
                        && (($this->yaml[$this->pos + $keyLineIndent + 1] ?? '') === ' '
                            || ($this->yaml[$this->pos + $keyLineIndent + 1] ?? '') === "\t"
                            || ($this->yaml[$this->pos + $keyLineIndent + 1] ?? '') === "\n"
                            || ($this->yaml[$this->pos + $keyLineIndent + 1] ?? '') === "\r"
                            || ($this->yaml[$this->pos + $keyLineIndent + 1] ?? '') === '')
                    ) {
                        $this->pos += $keyLineIndent;
                        $rawKey = $this->flowKeyToString($this->parseBlockSequence($indent));
                    } elseif ($keyLineIndent > $indent && $this->pos < $this->len) {
                        $this->pos += $keyLineIndent;
                        $rawKey = $this->flowKeyToString($this->parseNode($keyLineIndent));
                    } else {
                        $rawKey = null;
                    }
                } else {
                    $keyCol = $this->columnAt($this->pos);
                    $keyLineForMeta = $this->line;
                    $keyStr = $this->readPlainScalar($indent);
                    $this->skipSpaces();
                    $rawKey = $this->resolveScalar(trim($keyStr));

                    if ($this->flowDepth === 0 && ($this->yaml[$this->pos] ?? '') === ':' && $this->isBlockValueIndicator($this->pos)) {
                        $this->pos++;
                        $this->skipSpaces();
                        if ($this->captureMetadata) {
                            $this->pendingFirstKeyMetadataForMappingBody = new NodeMetadata(
                                line: $keyLineForMeta,
                                column: $keyCol,
                            );
                        }
                        $rawKey = $this->flowKeyToString($this->parseBlockMappingBody($keyCol, (string) $rawKey));
                    }
                }

                $this->skipSpaces();
                $key = $rawKey;

                if ($keyAnchor !== null) {
                    $this->anchors[$keyAnchor] = $this->deepCopy($rawKey);
                }

                $sameLineColon = false;
                if ($this->pos < $this->len && $this->yaml[$this->pos] === ':') {
                    $afterColon = $this->pos + 1 < $this->len ? $this->yaml[$this->pos + 1] : '';
                    if ($afterColon === ' ' || $afterColon === "\t" || $afterColon === "\n" || $afterColon === "\r" || $afterColon === ''
                        || ($this->flowDepth > 0 && ($afterColon === ',' || $afterColon === ']' || $afterColon === '}'))
                    ) {
                        $sameLineColon = true;
                    }
                }

                if (!$sameLineColon) {
                    $this->skipToEndOfLine();
                    $this->consumeNewline();
                    $this->skipBlankLinesAndComments();
                    $colonIndent = $this->countLineIndent();
                    if ($colonIndent >= $indent && $this->pos + $colonIndent < $this->len && $this->yaml[$this->pos + $colonIndent] === ':') {
                        $this->pos += $colonIndent;
                    }
                }

                if ($this->captureMetadata) {
                    $this->lastNodeMetadataTree = null;
                }
                if ($this->pos < $this->len && $this->yaml[$this->pos] === ':') {
                    $afterColon = $this->pos + 1 < $this->len ? $this->yaml[$this->pos + 1] : '';
                    if ($afterColon === ' ' || $afterColon === "\t" || $afterColon === "\n" || $afterColon === "\r" || $afterColon === ''
                        || ($this->flowDepth > 0 && ($afterColon === ',' || $afterColon === ']' || $afterColon === '}'))
                    ) {
                        $this->pos++;

                        $this->skipMappingIndicatorWhitespace(allowBlockScalar: true);
                        if ($this->captureMetadata) {
                            $valueLine = $this->line;
                            $valueColumn = $this->columnAt($this->pos);
                        }

                        $c2 = $this->yaml[$this->pos] ?? '';
                        if ($this->flowDepth > 0 && ($c2 === ',' || $c2 === ']' || $c2 === '}')) {
                            $value = null;
                        } elseif ($this->pos >= $this->len || $c2 === "\n" || $c2 === "\r") {
                            $this->skipToEndOfLine();
                            $this->consumeNewline();
                            $this->skipBlankLinesAndComments();
                            $nextIndent = $this->countLineIndent();
                            if ($nextIndent > $indent && $this->pos < $this->len) {
                                $this->pos += $nextIndent;
                                $value = $this->parseNode($nextIndent);
                            } else {
                                $value = null;
                            }
                        } else {
                            $lineStart = strrpos(substr($this->yaml, 0, $this->pos), "\n");
                            $valueCol = ($lineStart === false) ? $this->pos : ($this->pos - $lineStart - 1);
                            if ($valueCol < 0) {
                                $valueCol = 0;
                            }
                            $value = $this->parseNode($valueCol);
                            if (!$this->isAtLineStart()) {
                                $this->skipInlineComment();
                                $this->skipToEndOfLine();
                            }
                        }
                    } else {
                        $value = null;
                    }
                } else {
                    $value = null;
                }

                if ($this->captureMetadata) {
                    $this->captureMappingEntryMetadata($mappingMeta, $key, $explicitKeyMeta, $valueLine, $valueColumn);
                }

                $mapping[$key] = $value;

                $this->consumeNewline();
                $this->skipBlankLinesAndComments();

                if ($this->pos >= $this->len) {
                    break;
                }

                $nextIndent = $this->countLineIndent();
                if ($nextIndent < $indent) {
                    break;
                }

                $this->pos += $nextIndent;
                $nc = $this->yaml[$this->pos] ?? '';
                if ($nc === '?' || $nc === ':') {
                    continue;
                }
                if ($nc !== '-' && $nc !== '[' && $nc !== '{' && $nc !== '|' && $nc !== '>') {
                    $plainKeyMeta = $this->captureMetadata
                        ? new NodeMetadata(line: $this->line, column: $this->columnAt($this->pos))
                        : null;
                    $plainKey = $this->readPlainScalar($nextIndent);
                    $this->skipSpaces();
                    if ($this->pos < $this->len && $this->yaml[$this->pos] === ':') {
                        $afterColon = $this->pos + 1 < $this->len ? $this->yaml[$this->pos + 1] : '';
                        if ($afterColon === ' ' || $afterColon === "\t" || $afterColon === "\n" || $afterColon === "\r" || $afterColon === '') {
                            $this->pos++;
                            $this->skipSpaces();
                            $key = $plainKey;
                            if ($this->captureMetadata) {
                                $valueLine = $this->line;
                                $valueColumn = $this->columnAt($this->pos);
                            }
                            $plainValue = $this->parseNode($nextIndent);
                            if (!$this->isAtLineStart()) {
                                $this->skipToEndOfLine();
                            }
                            if ($this->captureMetadata) {
                                $this->captureMappingEntryMetadata($mappingMeta, $key, $plainKeyMeta, $valueLine, $valueColumn);
                            }
                            $mapping[$key] = $plainValue;
                            $this->consumeNewline();
                            $this->skipBlankLinesAndComments();
                            if ($this->pos >= $this->len) {
                                break;
                            }
                            $nextIndent = $this->countLineIndent();
                            if ($nextIndent < $indent) {
                                break;
                            }
                            $this->pos += $nextIndent;
                            $nc2 = $this->yaml[$this->pos] ?? '';
                            if ($nc2 === '?' || $nc2 === ':') {
                                continue;
                            }
                            $this->pos -= $nextIndent;
                            break;
                        }
                    }
                }
                $this->pos -= $nextIndent;
                break;
            }
            break;

        }

        $this->lastKeyWasExplicit = true;

        if ($this->captureMetadata) {
            $this->lastNodeMetadataTree = MetadataNode::mapping($mappingMeta, null);
        }

        return $mapping;
    }

    private function parseFlowMapping(): array
    {
        $mappingStartLine = $this->line;
        $mappingStartCol = $this->columnAt($this->pos);
        $mappingMeta = [];

        if ($this->flowDepth === 0) {
            $lineStart = $this->pos - $this->columnAt($this->pos);
            $beforeBrace = substr($this->yaml, $lineStart, $this->pos - $lineStart);
            $this->flowRootIndent = trim($beforeBrace, " \t") === ''
                ? -1
                : strspn($beforeBrace, " \t");
        }
        $rootIndent = $this->flowRootIndent;

        $this->pos++;
        $this->flowDepth++;
        $crossed = $this->skipFlowWhitespace();
        $this->guardFlowContinuationIndent($crossed, $rootIndent);

        $mapping = [];
        $foundClosing = false;
        $isFirstPair = true;
        $hasPair = false;

        while ($this->pos < $this->len) {
            $c = $this->yaml[$this->pos];

            if ($c === '}') {
                $this->pos++;
                $this->flowDepth--;
                $foundClosing = true;
                break;
            }

            if ($c === ',') {
                if (!$hasPair && $isFirstPair) {
                    throw new ParserException("Unexpected comma at beginning of flow mapping at line {$this->line}");
                }
                $this->pos++;
                $crossed = $this->skipFlowWhitespace();
                $this->guardFlowContinuationIndent($crossed, $rootIndent);
                $isFirstPair = false;
                continue;
            }

            $hasPair = true;
            $isFirstPair = false;

            if ($c === '?') {
                $this->pos++;
                $this->skipFlowWhitespace();
                $c = $this->yaml[$this->pos] ?? '';
            }

            if ($this->captureMetadata) {
                $keyMeta = new NodeMetadata(line: $this->line, column: $this->columnAt($this->pos));
                $this->lastNodeMetadataTree = null;
            }

            if ($c === "'") {
                $key = $this->readSingleQuotedScalar();
            } elseif ($c === '"') {
                $key = $this->readDoubleQuotedScalar();
            } elseif ($c === '!') {
                $key = $this->parseTaggedNode(0);
            } elseif ($c === '[' || $c === '{' || $c === '*' || $c === '&') {
                $previousSuppress = $this->suppressFlowPairKey;
                $this->suppressFlowPairKey = true;
                try {
                    $keyNode = $this->parseNode(0);
                } finally {
                    $this->suppressFlowPairKey = $previousSuppress;
                }
                $key = $this->flowKeyToString($keyNode);
            } else {
                $key = $this->readPlainScalarFlow();
                if ($key === '') {
                    $key = 'null';
                }
            }
            $crossed = $this->skipFlowWhitespace();

            if ($this->captureMetadata) {
                $this->lastNodeMetadataTree = null;
                $valueLine = $this->line;
                $valueColumn = $this->columnAt($this->pos);
            }

            if ($this->pos >= $this->len || $this->yaml[$this->pos] === ',' || $this->yaml[$this->pos] === '}') {
                $value = null;
            } elseif ($this->yaml[$this->pos] !== ':') {
                throw new ParserException("Expected ':' in flow mapping at line {$this->line}");
            } else {
                $this->guardFlowContinuationIndent($crossed, $rootIndent);
                $this->pos++;
                $crossed = $this->skipFlowWhitespace();
                $this->guardFlowContinuationIndent($crossed, $rootIndent);
                if ($this->captureMetadata) {
                    $valueLine = $this->line;
                    $valueColumn = $this->columnAt($this->pos);
                }

                $c2 = $this->yaml[$this->pos] ?? '';
                if ($c2 === '}' || $c2 === ',') {
                    $value = null;
                } else {
                    $posBefore = $this->pos;
                    $previousInFlowMappingValue = $this->inFlowMappingValue;
                    $this->inFlowMappingValue = true;
                    try {
                        $value = $this->parseNode(0);
                    } finally {
                        $this->inFlowMappingValue = $previousInFlowMappingValue;
                    }
                    if ($this->pos === $posBefore) {
                        $this->pos++;
                    }
                }
            }
            $crossed = $this->skipFlowWhitespace();
            $this->guardFlowContinuationIndent($crossed, $rootIndent);

            if ($this->pos < $this->len && ($this->yaml[$this->pos] ?? '') !== ',' && ($this->yaml[$this->pos] ?? '') !== '}') {
                throw new ParserException("Expected ',' or '}' in flow mapping at line {$this->line}");
            }

            if ($this->captureMetadata) {
                $this->captureMappingEntryMetadata($mappingMeta, $key, $keyMeta, $valueLine, $valueColumn);
            }

            if ($key === '<<') {
                $this->mergeIntoMapping($mapping, $value);
            } else {
                $mapping[$key] = $value;
            }

            if ($this->pos < $this->len && $this->yaml[$this->pos] === ',') {
                $this->pos++;
                $this->skipFlowWhitespace();
            }
        }

        if (!$foundClosing) {
            throw new ParserException("Flow mapping without closing bracket at line {$this->line}");
        }

        $this->skipSpaces();

        if ($this->flowDepth === 0 && $this->pos < $this->len && $this->yaml[$this->pos] === '}') {
            throw new ParserException("Unexpected extra closing bracket '}' at line {$this->line}");
        }

        $this->validateFlowCollectionTrailingContent();

        if ($this->captureMetadata) {
            $this->lastNodeMetadataTree = MetadataNode::mapping(
                $mappingMeta,
                new NodeMetadata(line: $mappingStartLine, column: $mappingStartCol),
            );
        }

        return $mapping;
    }

    /**
     * Convert a parsed flow node into a usable PHP array key.
     *
     * PHP arrays only accept string/int keys, so complex flow keys (sequences,
     * mappings) are rendered back into their flow representation.
     */
    private function flowKeyToString(mixed $key): string
    {
        if (is_string($key)) {
            return $key;
        }
        if ($key === null) {
            return 'null';
        }
        if (is_bool($key)) {
            return $key ? 'true' : 'false';
        }
        if (!is_array($key)) {
            return (string) $key;
        }

        if (count($key) === 1 && array_key_exists(self::FORWARD_ALIAS_MARKER, $key)) {
            $aliasName = (string) $key[self::FORWARD_ALIAS_MARKER];
            if (!array_key_exists($aliasName, $this->anchors)) {
                throw new ResolverException("Unknown alias: *{$aliasName}");
            }

            return $this->flowKeyToString($this->anchors[$aliasName]);
        }

        if (array_key_exists(self::ANCHOR_MARKER, $key) && array_key_exists(self::ANCHOR_VALUE_MARKER, $key)) {
            return $this->flowKeyToString($key[self::ANCHOR_VALUE_MARKER]);
        }

        return json_encode($this->normaliseFlowKeyValue($key), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
    }

    /**
     * Resolve anchor/alias markers so a complex key can be JSON encoded.
     */
    private function normaliseFlowKeyValue(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if (count($value) === 1 && array_key_exists(self::FORWARD_ALIAS_MARKER, $value)) {
            $aliasName = (string) $value[self::FORWARD_ALIAS_MARKER];
            if (!array_key_exists($aliasName, $this->anchors)) {
                throw new ResolverException("Unknown alias: *{$aliasName}");
            }

            return $this->normaliseFlowKeyValue($this->anchors[$aliasName]);
        }

        if (array_key_exists(self::ANCHOR_MARKER, $value) && array_key_exists(self::ANCHOR_VALUE_MARKER, $value)) {
            return $this->normaliseFlowKeyValue($value[self::ANCHOR_VALUE_MARKER]);
        }

        $result = [];
        foreach ($value as $k => $v) {
            $result[$k] = $this->normaliseFlowKeyValue($v);
        }

        return $result;
    }

    private function skipMappingIndicatorWhitespace(bool $allowBlockScalar): void
    {
        $this->pos += strspn($this->yaml, ' ', $this->pos);

        if ($this->pos < $this->len && $this->yaml[$this->pos] === "\t") {
            $nextContentPos = $this->pos + 1;
            while ($nextContentPos < $this->len && ($this->yaml[$nextContentPos] === "\t" || $this->yaml[$nextContentPos] === ' ')) {
                $nextContentPos++;
            }

            $nextContent = $this->yaml[$nextContentPos] ?? '';
            $allowedAfterTab = $nextContent === ''
                || $nextContent === "\n"
                || $nextContent === "\r"
                || $nextContent === '#'
                || ($allowBlockScalar && ($nextContent === '|' || $nextContent === '>'));

            if (!$allowedAfterTab) {
                throw new LexerException("Tab character cannot be used as separator at line {$this->line}, column {$this->columnAt($this->pos)}");
            }
        }

        $this->pos += strspn($this->yaml, "\t ", $this->pos);
    }

    private function captureMappingEntryMetadata(
        array &$mappingMeta,
        mixed $key,
        ?NodeMetadata $keyMetadata,
        int $valueLine,
        int $valueColumn,
    ): void {
        $mappingMeta[$key] = [
            'key' => $keyMetadata,
            'value' => $this->metadataForLastParsedValue($valueLine, $valueColumn),
        ];
    }

    private function mergeIntoMapping(array &$target, mixed $mergeValue): void
    {
        if (is_array($mergeValue) && array_is_list($mergeValue)) {
            for ($i = count($mergeValue) - 1; $i >= 0; $i--) {
                $this->mergeSingleMapping($target, $mergeValue[$i] ?? null);
            }

            return;
        }

        $this->mergeSingleMapping($target, $mergeValue);
    }

    private function mergeSingleMapping(array &$target, mixed $mergeValue): void
    {
        if (is_array($mergeValue) && array_key_exists(self::FORWARD_ALIAS_MARKER, $mergeValue) && count($mergeValue) === 1) {
            $aliasName = (string) $mergeValue[self::FORWARD_ALIAS_MARKER];
            if (!array_key_exists($aliasName, $this->anchors)) {
                throw new ResolverException("Unknown alias: *{$aliasName}");
            }
            $mergeValue = $this->deepCopy($this->anchors[$aliasName]);
        }

        if (!is_array($mergeValue)) {
            throw new ParserException('Merge key value must be a mapping, not ' . gettype($mergeValue) . " at line {$this->line}");
        }
        foreach ($mergeValue as $k => $v) {
            if (!array_key_exists($k, $target)) {
                $target[$k] = $this->deepCopy($v);
            }
        }
    }
}
