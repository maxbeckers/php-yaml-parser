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
 * Anchor, alias and tag scanning plus post-parse alias resolution.
 *
 * This trait is an internal implementation detail of the scanner-parser and
 * is not part of the public API. It operates directly on the scan state
 * declared by ScanParser ($pos, $line, $yaml, ...).
 */
trait AnchorAliasTagScannerTrait
{
    private function parseAnchoredNode(int $indent, ?int $nodeCol = null): ?array
    {
        $this->hasAnchorsOrAliases = true;
        $anchorCol = $nodeCol ?? $this->columnAt($this->pos);
        $this->pos++;
        $end = strcspn($this->yaml, " \t\r\n,{}[]", $this->pos);
        if ($end === 0) {
            throw new ParserException("Invalid anchor: empty anchor name at line {$this->line}");
        }
        $anchorName = substr($this->yaml, $this->pos, $end);
        $this->pos += $end;
        $this->skipSpaces();

        $c = $this->pos < $this->len ? $this->yaml[$this->pos] : '';
        if ($c === '*') {
            throw new ParserException("A node cannot be both anchored and an alias at line {$this->line}");
        }
        if ($c === '-' && $this->flowDepth === 0) {
            $next = $this->pos + 1 < $this->len ? $this->yaml[$this->pos + 1] : '';
            if ($next === ' ' || $next === "\t" || $next === "\n" || $next === "\r" || $next === '') {
                throw new ParserException("A block sequence entry cannot start inline after an anchor at line {$this->line}");
            }
        }
        if ($c === "\n" || $c === "\r" || $c === '#' || $c === '') {
            $this->skipToEndOfLine();
            $this->consumeNewline();
            $this->skipBlankLinesAndComments();

            if ($this->pos >= $this->len) {
                $this->anchors[$anchorName] = null;
                if ($this->captureMetadata) {
                    $this->pendingAnchorForMetadata = $anchorName;
                }

                return null;
            }

            $nextIndent = $this->countLineIndent();
            if ($nextIndent < $indent || ($nextIndent === $indent && $anchorCol !== $indent)) {
                $this->anchors[$anchorName] = null;
                if ($this->captureMetadata) {
                    $this->pendingAnchorForMetadata = $anchorName;
                }

                return null;
            }

            $this->pos += $nextIndent;
            if (($this->yaml[$this->pos] ?? '') === '&') {
                $peek = $this->pos + 1;
                $peekEnd = $peek + strcspn($this->yaml, " \t\r\n,{}[]", $peek);
                $afterName = $peekEnd + strspn($this->yaml, " \t", $peekEnd);
                $lineEnd = $afterName + strcspn($this->yaml, "\n\r", $afterName);
                $restOfLine = substr($this->yaml, $afterName, $lineEnd - $afterName);
                if (!$this->lineContainsKeyIndicator($restOfLine)) {
                    throw new ParserException("A node cannot have two anchors at line {$this->line}");
                }
            }
            $value = $this->parseNode($nextIndent);
        } else {
            $value = $this->parseNode($indent, $anchorCol);
        }

        if ($this->captureMetadata) {
            $childMetadataTree = $this->lastNodeMetadataTree;
            $this->pendingAnchorForMetadata = $anchorName;
            $this->lastNodeMetadataTree = $childMetadataTree;
        }

        $this->anchors[$anchorName] = $this->deepCopy($value);

        return [
            self::ANCHOR_MARKER => $anchorName,
            self::ANCHOR_VALUE_MARKER => $value,
        ];
    }

    private function parseAliasNode(): array
    {
        $this->hasAnchorsOrAliases = true;
        $this->pos++; // skip '*'
        $end = strcspn($this->yaml, " \t\r\n,{}[]", $this->pos);
        if ($end === 0) {
            throw new ParserException("Invalid alias: empty alias name at line {$this->line}");
        }
        $aliasName = substr($this->yaml, $this->pos, $end);
        $this->pos += $end;
        $this->skipSpaces();

        if ($this->captureMetadata) {
            $aliasMeta = new NodeMetadata(
                alias: $aliasName,
                line: $this->line,
                column: $this->columnAt($this->pos - strlen($aliasName) - 1),
            );
            $this->lastNodeMetadataTree = MetadataNode::scalar($aliasMeta);
        }

        return [self::FORWARD_ALIAS_MARKER => $aliasName];
    }

    private function parseTaggedNode(int $indent, ?int $nodeCol = null): mixed
    {
        $tagLine = $this->line;
        $tagCol = $nodeCol ?? $this->columnAt($this->pos);

        if (($this->yaml[$this->pos] ?? '') === '!' && ($this->yaml[$this->pos + 1] ?? '') === '<') {
            $closeAngle = strpos($this->yaml, '>', $this->pos);
            $end = $closeAngle !== false ? ($closeAngle - $this->pos + 1) : strcspn($this->yaml, " \t\r\n", $this->pos);
        } else {
            $stopChars = $this->flowDepth > 0 ? " \t\r\n,}]" : " \t\r\n,";
            $end = strcspn($this->yaml, $stopChars, $this->pos);
        }
        $tag = substr($this->yaml, $this->pos, $end);
        $this->pos += $end;
        $this->skipSpaces();

        if (str_starts_with($tag, '!<')) {
            $inner = substr($tag, 2, -1); // strip !< and >
            if ($inner === '' || $inner === '!' || !preg_match('/^[a-zA-Z0-9\-._~:@!()*+,;=%\/&\[\]]+$/', $inner)) {
                throw new LexerException("Invalid verbatim tag format '$tag' in line $tagLine, column $tagCol");
            }
        }

        if (!str_starts_with($tag, '!<') && $tag !== '!') {
            if (preg_match('/^(!(?:[a-zA-Z0-9]+)?!)(.*)$/', $tag, $m)) {
                $handle = $m[1];
                $suffix = $m[2];
                if ($suffix === '') {
                    throw new LexerException("Invalid tag format '$tag' in line $tagLine, column $tagCol");
                }
                if ($handle !== '!!' && !isset($this->tagHandles[$handle])) {
                    throw new LexerException("Undefined tag handle '$handle' in line $tagLine, column $tagCol");
                }
            } else {
                if (!preg_match('/^![a-zA-Z0-9\-_:.]*$/', $tag)) {
                    throw new LexerException("Invalid tag format '$tag': tags can only contain alphanumeric characters, hyphens, underscores, dots, and colons in line $tagLine, column $tagCol");
                }
            }
        }

        $c = $this->yaml[$this->pos] ?? '';
        if ($c === ',' && $this->flowDepth === 0) {
            throw new LexerException("Invalid comma after tag at line {$this->line}");
        }

        if ($tag === '!' && $c !== "\n" && $c !== "\r" && $c !== '#' && $c !== '' && $c !== ',' && $c !== '}' && $c !== ']') {
            $rawScalar = $this->readPlainScalar($indent);
            if ($this->captureMetadata) {
                $this->pendingTagForMetadata = $tag;
            }

            return is_array($rawScalar) ? $rawScalar : (string) $rawScalar;
        }

        if ($c === "\n" || $c === "\r" || $c === '#') {
            $this->skipToEndOfLine();
            $this->consumeNewline();
            $this->skipBlankLinesAndComments();

            if ($this->pos >= $this->len) {
                $value = null;
            } else {
                $nextIndent = $this->countLineIndent();
                $nc = $this->yaml[$this->pos + $nextIndent] ?? '';
                if ($nextIndent < $indent) {
                    $ncNext = $this->yaml[$this->pos + $nextIndent + 1] ?? '';
                    if ($nc === '-' && ($ncNext === ' ' || $ncNext === "\t" || $ncNext === "\n" || $ncNext === "\r" || $ncNext === '')) {
                        $this->pos += $nextIndent;
                        $value = $this->parseBlockSequence($nextIndent);
                    } elseif (($nc === '|' || $nc === '>') && ($ncNext === '' || $ncNext === "\n" || $ncNext === "\r" || ctype_digit($ncNext))) {
                        $this->pos += $nextIndent;
                        $value = $nc === '|' ? $this->parseLiteralBlock(-1) : $this->parseFoldedBlock(-1);
                    } else {
                        $value = null;
                    }
                } else {
                    $this->pos += $nextIndent;
                    $value = $this->parseNode($nextIndent);
                }
            }
        } elseif ($c === '' || $c === ',' || $c === '}' || $c === ']' || ($c === ':' && $this->flowDepth > 0)) {
            $value = null;
        } else {
            $value = $this->parseNode($indent, $tagCol);
        }

        if ($this->captureMetadata) {
            $this->pendingTagForMetadata = $tag;
        }

        return $this->applyKnownTag($tag, $value, $tagLine, $tagCol);
    }

    private function applyKnownTag(string $tag, mixed $value, int $tagLine, int $tagCol): mixed
    {
        $expandedTag = $tag;
        foreach ($this->tagHandles as $handle => $prefix) {
            if ($handle === '!!' || $handle === '!') {
                continue;
            }
            if (str_starts_with($tag, $handle)) {
                $expandedTag = '!<' . $prefix . substr($tag, strlen($handle)) . '>';
                break;
            }
        }
        if (str_starts_with($tag, '!!') && $this->tagHandles['!!'] !== 'tag:yaml.org,2002:') {
            $expandedTag = '!<' . $this->tagHandles['!!'] . substr($tag, 2) . '>';
        } elseif ($tag !== '!' && str_starts_with($tag, '!') && !str_starts_with($tag, '!<') && $this->tagHandles['!'] !== '!') {
            $expandedTag = '!<' . $this->tagHandles['!'] . substr($tag, 1) . '>';
        }

        return match ($expandedTag) {
            '!!str', '!<tag:yaml.org,2002:str>' => is_array($value) ? $value : (string) $value,
            '!' => is_string($value) ? $value : (string) ($value ?? ''),
            '!!int', '!<tag:yaml.org,2002:int>' => is_array($value) ? $value : (int) $value,
            '!!float', '!<tag:yaml.org,2002:float>' => is_array($value) ? $value : (float) $value,
            '!!bool', '!<tag:yaml.org,2002:bool>' => is_array($value) ? $value : (bool) $value,
            '!!null', '!<tag:yaml.org,2002:null>' => is_array($value) ? $value : null,
            '!!binary', '!<tag:yaml.org,2002:binary>' => is_array($value) ? $value : base64_decode((string) $value, true),
            '!!timestamp' => $this->parseTimestampTag($value),
            '!!map', '!<tag:yaml.org,2002:map>' => is_array($value) && !array_is_list($value) ? $value
                : throw new ParserException("Tag !!map requires mapping value at line {$this->line}"),
            '!!seq', '!<tag:yaml.org,2002:seq>' => is_array($value) && array_is_list($value) ? $value
                : throw new ParserException("Tag !!seq requires sequence value at line {$this->line}"),
            default => $this->applyCustomTag($tag, $expandedTag, $value, $tagLine, $tagCol),
        };
    }

    private function applyCustomTag(string $tag, string $expandedTag, mixed $value, int $tagLine, int $tagCol): mixed
    {
        $handler = $this->tagRegistry?->getHandler($tag);
        if ($handler === null && $expandedTag !== $tag) {
            $handler = $this->tagRegistry?->getHandler($expandedTag);
        }

        if ($handler === null) {
            return $value;
        }

        return $handler->handle(
            $value,
            new NodeMetadata(tag: $tag, line: $tagLine, column: $tagCol),
        );
    }

    private function parseTimestampTag(mixed $value): ?\DateTimeImmutable
    {
        if (is_array($value)) {
            return null;
        }

        if ($value === null || $value === '') {
            return null;
        }
        try {
            return new \DateTimeImmutable((string) $value);
        } catch (\Exception) {
            throw new ParserException("Invalid timestamp value at line {$this->line}");
        }
    }

    private function resolveForwardAliases(mixed $value, array &$resolvedAnchors = [], array &$resolving = []): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if (array_key_exists(self::FORWARD_ALIAS_MARKER, $value) && count($value) === 1) {
            return $this->resolveAliasValue((string) $value[self::FORWARD_ALIAS_MARKER], $resolvedAnchors, $resolving);
        }

        if (array_key_exists(self::ANCHOR_MARKER, $value) && array_key_exists(self::ANCHOR_VALUE_MARKER, $value)) {
            $anchorName = (string) $value[self::ANCHOR_MARKER];
            $anchorValue = $value[self::ANCHOR_VALUE_MARKER];

            if (!array_key_exists($anchorName, $resolvedAnchors)) {
                $resolvedAnchors[$anchorName] = is_array($anchorValue) ? new \ArrayObject() : $anchorValue;
            }

            $resolvedValue = $this->resolveForwardAliases($anchorValue, $resolvedAnchors, $resolving);
            if ($resolvedValue instanceof \ArrayObject) {
                if ($resolvedAnchors[$anchorName] instanceof \ArrayObject) {
                    $resolvedAnchors[$anchorName]->exchangeArray($resolvedValue->getArrayCopy());
                } else {
                    $resolvedAnchors[$anchorName] = $resolvedValue;
                }

                return $resolvedAnchors[$anchorName];
            }

            $resolvedAnchors[$anchorName] = $resolvedValue;

            return $resolvedValue;
        }

        $result = new \ArrayObject();
        foreach ($value as $key => $item) {
            $result[$key] = $this->resolveForwardAliases($item, $resolvedAnchors, $resolving);
        }

        return $result;
    }

    private function resolveAliasValue(string $aliasName, array &$resolvedAnchors, array &$resolving): mixed
    {
        if (!array_key_exists($aliasName, $this->anchors)) {
            throw new ResolverException("Unknown alias: *{$aliasName}");
        }

        if (array_key_exists($aliasName, $resolvedAnchors)) {
            return $resolvedAnchors[$aliasName];
        }

        if (isset($resolving[$aliasName])) {
            return $resolvedAnchors[$aliasName] ?? null;
        }

        $rawAnchorValue = $this->anchors[$aliasName];
        if (!is_array($rawAnchorValue)) {
            $resolvedAnchors[$aliasName] = $rawAnchorValue;

            return $rawAnchorValue;
        }

        $resolvedAnchors[$aliasName] = new \ArrayObject();
        $resolving[$aliasName] = true;
        $resolvedValue = $this->resolveForwardAliases($rawAnchorValue, $resolvedAnchors, $resolving);
        unset($resolving[$aliasName]);

        if ($resolvedValue instanceof \ArrayObject) {
            $resolvedAnchors[$aliasName]->exchangeArray($resolvedValue->getArrayCopy());

            return $resolvedAnchors[$aliasName];
        }

        $resolvedAnchors[$aliasName] = $resolvedValue;

        return $resolvedValue;
    }

    private function deepCopy(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        foreach ($value as $item) {
            if (is_array($item)) {
                return array_map(function ($v) {
                    return $this->deepCopy($v);
                }, $value);
            }
        }

        return $value;
    }
}
