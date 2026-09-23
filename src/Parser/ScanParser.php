<?php

namespace MaxBeckers\YamlParser\Parser;

use MaxBeckers\YamlParser\Format\Version;
use MaxBeckers\YamlParser\Exception\LexerException;
use MaxBeckers\YamlParser\Exception\ParserException;
use MaxBeckers\YamlParser\Exception\ParserException as YamlParserParserException;
use MaxBeckers\YamlParser\Metadata\MetadataNode;
use MaxBeckers\YamlParser\Node\NodeMetadata;
use MaxBeckers\YamlParser\Parser\Scan\AnchorAliasTagScannerTrait;
use MaxBeckers\YamlParser\Parser\Scan\BlockScalarScannerTrait;
use MaxBeckers\YamlParser\Parser\Scan\MappingScannerTrait;
use MaxBeckers\YamlParser\Parser\Scan\ScalarScannerTrait;
use MaxBeckers\YamlParser\Parser\Scan\SequenceScannerTrait;
use MaxBeckers\YamlParser\Tag\TagRegistry;

/**
 * Single-pass scanner-parser for the direct (fast) path.
 *
 * Works directly on the raw YAML string with an integer position cursor.
 * No Token objects, no Generator overhead, and no TokenStream buffer.
 *
 * Unsupported constructs are reported explicitly so the direct parser can
 * stay small, fast, and predictable.
 */
final class ScanParser
{
    use AnchorAliasTagScannerTrait;
    use BlockScalarScannerTrait;
    use MappingScannerTrait;
    use ScalarScannerTrait;
    use SequenceScannerTrait;

    public const FORWARD_ALIAS_MARKER = '__yaml_forward_alias__';
    public const ANCHOR_MARKER = '__yaml_anchor__';
    public const ANCHOR_VALUE_MARKER = '__yaml_anchor_value__';

    private int $pos = 0;
    private int $len;
    private int $line = 1;
    private int $flowDepth = 0;
    private int $depth = 0;
    private Version $version;
    private ?int $maxDepth;
    private bool $strictMode;
    private bool $yamlDirectiveSeen = false;
    private bool $lastWasDocumentEnd = false;
    private bool $suppressFlowPairKey = false;
    private bool $lastPlainScalarInFlowHadNewline = false;
    private bool $lastKeyWasExplicit = false;
    private bool $inFlowMappingValue = false;
    private bool $lastScalarStoppedAtComment = false;
    private bool $rejectTabQuotedIndent = false;
    private ?MetadataNode $lastNodeMetadataTree = null;
    private ?string $pendingAnchorForMetadata = null;
    private ?string $pendingTagForMetadata = null;
    private ?NodeMetadata $pendingFirstKeyMetadataForMappingBody = null;
    /** @var array<int, MetadataNode|null> */
    private array $documentMetadataTrees = [];
    private bool $hasAnchorsOrAliases = false;
    /** @var array<string, mixed> */
    private array $anchors = [];
    /** @var array<string, string> */
    private array $tagHandles = ['!!' => 'tag:yaml.org,2002:', '!' => '!'];
    /** @var array<string, bool> */
    private array $tagHandlesDefined = [];
    private bool $tagHandlesFreshForDocument = false;
    private int $flowRootIndent = 0;

    public function __construct(
        private readonly string $yaml,
        Version $version = Version::VERSION_1_2,
        ?int $maxDepth = null,
        bool $strictMode = true,
        private readonly bool $preferPlainArrays = false,
        private readonly bool $captureMetadata = false,
        private readonly ?TagRegistry $tagRegistry = null,
    ) {
        $this->len = strlen($yaml);
        $this->version = $version;
        $this->maxDepth = $maxDepth;
        $this->strictMode = $strictMode;
    }

    public function parseDocuments(): mixed
    {
        $documents = [];

        if ($this->len >= 3 && str_starts_with($this->yaml, "\xEF\xBB\xBF")) {
            $this->pos = 3;
        }

        while ($this->pos < $this->len) {
            $this->skipBlankLinesAndComments();

            if ($this->pos >= $this->len) {
                break;
            }

            $c = $this->yaml[$this->pos];

            if ($c === '%') {
                if (count($documents) > 0 && !$this->lastWasDocumentEnd) {
                    throw new ParserException("Directive cannot appear after document content at line {$this->line}");
                }
                $this->lastWasDocumentEnd = false;
                $this->parseDirectiveLine();

                $this->skipBlankLinesAndComments();
                if ($this->pos >= $this->len) {
                    throw new ParserException("Directive must be followed by a document at line {$this->line}");
                }

                $nextC = $this->yaml[$this->pos];
                if ($nextC === '%') {
                    continue;
                }
                if ($nextC === '.' && $this->isDocMarker('...')) {
                    throw new ParserException("Document end marker without preceding document at line {$this->line}");
                }
                continue;
            }

            if ($c === '-' && $this->isDocMarker('---')) {
                $this->pos += 3;
                $this->yamlDirectiveSeen = false;
                $this->lastWasDocumentEnd = false;
                if (!$this->tagHandlesFreshForDocument) {
                    $this->tagHandlesDefined = [];
                    $this->tagHandles = ['!!' => 'tag:yaml.org,2002:', '!' => '!'];
                }
                $this->tagHandlesFreshForDocument = false;
                $this->skipSpaces();

                $inline = $this->yaml[$this->pos] ?? '';
                if ($inline !== '' && $inline !== "\n" && $inline !== "\r" && $inline !== '#') {
                    if ($inline === '&') {
                        $lineEnd = $this->pos + strcspn($this->yaml, "\n\r", $this->pos);
                        $restOfLine = substr($this->yaml, $this->pos, $lineEnd - $this->pos);
                        if (preg_match('/^&\S+[ \t]+([^\s:{}\[\]"\'#][^:\r\n]*):(\s|$)/', $restOfLine) === 1) {
                            throw new ParserException("Anchor cannot precede an implicit mapping key on the document start line at line {$this->line}");
                        }
                    }
                    if ($inline === '|' || $inline === '>') {
                        $blockScalarLine = $this->line;
                        $blockScalarCol = $this->columnAt($this->pos);
                        $documents[] = $this->parseTopLevelBlockScalar();
                        if ($this->captureMetadata) {
                            $this->documentMetadataTrees[] = MetadataNode::scalar(
                                new NodeMetadata(line: $blockScalarLine, column: $blockScalarCol),
                            );
                        }
                    } else {
                        $documents[] = $this->parseNode(0);
                        if ($this->captureMetadata) {
                            $this->documentMetadataTrees[] = $this->lastNodeMetadataTree;
                        }
                    }
                    if (!$this->isAtLineStart()) {
                        $this->skipTrailingCommentOrFailOnContent('document');
                    }
                    $this->consumeNewline();
                    continue;
                }

                $this->skipToEndOfLine();
                $this->consumeNewline();
                $this->skipBlankLinesAndComments();

                if ($this->pos >= $this->len || $this->isDocMarker('...')) {
                    $documents[] = null;
                    if ($this->captureMetadata) {
                        $this->documentMetadataTrees[] = MetadataNode::scalar(null);
                    }
                    if ($this->pos < $this->len && $this->isDocMarker('...')) {
                        $this->pos += 3;
                        $this->yamlDirectiveSeen = false;
                        $this->lastWasDocumentEnd = true;
                        $this->skipToEndOfLine();
                        $this->consumeNewline();
                    }
                    continue;
                }

                $documents[] = $this->parseTopLevelNode();
                if ($this->captureMetadata) {
                    $this->documentMetadataTrees[] = $this->lastNodeMetadataTree;
                }
                continue;
            }

            if ($c === '.' && $this->isDocMarker('...')) {
                $this->pos += 3;
                $this->yamlDirectiveSeen = false;
                $this->lastWasDocumentEnd = true;
                $this->tagHandlesDefined = [];
                $this->tagHandles = ['!!' => 'tag:yaml.org,2002:', '!' => '!'];
                $this->tagHandlesFreshForDocument = false;
                $this->skipSpaces();

                if ($this->pos < $this->len && $this->yaml[$this->pos] !== "\n" && $this->yaml[$this->pos] !== "\r" && $this->yaml[$this->pos] !== '#') {
                    throw new ParserException("Invalid content after document end marker at line {$this->line}");
                }

                $this->skipToEndOfLine();
                $this->consumeNewline();
                continue;
            }

            $posBefore = $this->pos;

            if (count($documents) > 0) {
                $checkIndent = $this->countLineIndent();
                if ($checkIndent === 0) {
                    $checkPos = $this->pos + $checkIndent;
                    if ($checkPos < $this->len) {
                        $checkChar = $this->yaml[$checkPos];
                        if (!in_array($checkChar, ['[', '{', '!', '\'', '"', '|', '>', '*', '-', '?', '%', '.'], true)) {
                            $lookahead = strcspn($this->yaml, ":\n\r", $checkPos);
                            $afterLookahead = $checkPos + $lookahead < $this->len ? $this->yaml[$checkPos + $lookahead] : '';

                            if ($afterLookahead !== ':') {
                                throw new ParserException("Unexpected plain scalar at root level after content at line {$this->line}");
                            }
                        }
                    }
                }
            }

            $c2 = $this->yaml[$this->pos] ?? '';
            if ($c2 === '-' && $this->isDocMarker('---')) {
                continue;
            }
            if ($c2 === '.' && $this->isDocMarker('...')) {
                continue;
            }

            $this->lastWasDocumentEnd = false;
            $documents[] = $this->parseTopLevelNode();
            if ($this->captureMetadata) {
                $this->documentMetadataTrees[] = $this->lastNodeMetadataTree;
            }
            if ($this->pos === $posBefore && $this->pos < $this->len) {
                $this->pos++;
            }

            $this->skipBlankLinesAndComments();
            if ($this->pos < $this->len
                && !$this->isDocMarker('---')
                && !$this->isDocMarker('...')
                && ($this->yaml[$this->pos] ?? '') !== '%'
            ) {
                throw new ParserException("Unexpected content after document at line {$this->line}");
            }
        }

        return ($this->hasAnchorsOrAliases || !$this->preferPlainArrays)
            ? $this->resolveForwardAliases($documents)
            : $documents;
    }

    /**
     * True when the parsed document tree contains no anchors or aliases,
     * meaning the result is guaranteed to be free of ArrayObject wrapper
     * nodes (see parseDocuments()). Callers that requested plain arrays can
     * use this to skip a redundant ArrayObject → array conversion pass.
     */
    /**
     * @return list<MetadataNode> one metadata tree per parsed document,
     *                            populated only when captureMetadata is enabled
     */
    public function getDocumentMetadataTrees(): array
    {
        return $this->documentMetadataTrees;
    }

    public function hasAnchorsOrAliases(): bool
    {
        return $this->hasAnchorsOrAliases;
    }

    private function metadataForLastParsedValue(int $fallbackLine, int $fallbackColumn): MetadataNode
    {
        return $this->lastNodeMetadataTree
            ?? MetadataNode::scalar(new NodeMetadata(line: $fallbackLine, column: $fallbackColumn));
    }

    private function captureSinglePairMetadata(
        int|string $key,
        int $keyLine,
        int $keyColumn,
        int $valueLine,
        int $valueColumn,
    ): void {
        $this->lastNodeMetadataTree = MetadataNode::mapping(
            [
                $key => [
                    'key' => new NodeMetadata(line: $keyLine, column: $keyColumn),
                    'value' => $this->metadataForLastParsedValue($valueLine, $valueColumn),
                ],
            ],
            null,
        );
    }

    private function parseTopLevelNode(): mixed
    {
        $whitespace = strspn($this->yaml, " \t", $this->pos);
        if ($whitespace > 0 && str_contains(substr($this->yaml, $this->pos, $whitespace), "\t")) {
            $first = $this->yaml[$this->pos + $whitespace] ?? '';
            if ($first === '[' || $first === '{') {
                $this->pos += $whitespace;

                return $this->parseNode(0);
            }
        }

        $indent = $this->countLineIndent();
        $this->pos += $indent;

        $c = $this->yaml[$this->pos] ?? '';
        if ($indent === 0 && ($c === '|' || $c === '>')) {
            return $this->parseTopLevelBlockScalar();
        }

        return $this->parseNode($indent);
    }

    /**
     * Parse a YAML node.
     *
     * @param int $indent column of the first character (spaces before it)
     */
    private function parseNode(int $indent, ?int $nodeCol = null): mixed
    {
        if (!$this->captureMetadata) {
            return $this->parseNodeCore($indent, $nodeCol);
        }

        $startLine = $this->line;
        $startCol = $nodeCol ?? $this->columnAt($this->pos);
        $this->pendingAnchorForMetadata = null;
        $this->pendingTagForMetadata = null;
        $this->lastNodeMetadataTree = null;

        $value = $this->parseNodeCore($indent, $nodeCol);

        $ownMeta = new NodeMetadata(
            tag: $this->pendingTagForMetadata,
            anchor: $this->pendingAnchorForMetadata,
            line: $startLine,
            column: $startCol,
        );
        $childTree = $this->lastNodeMetadataTree;
        $this->lastNodeMetadataTree = $childTree !== null
            ? $childTree->withOwnMetadata($ownMeta)
            : MetadataNode::scalar($ownMeta);

        return $value;
    }

    private function parseNodeCore(int $indent, ?int $nodeCol = null): mixed
    {
        if ($this->pos >= $this->len) {
            return null;
        }

        if ($this->maxDepth !== null && $this->depth >= $this->maxDepth) {
            throw new YamlParserParserException("Maximum parsing depth of {$this->maxDepth} exceeded");
        }

        $this->depth++;

        try {
            $c = $this->yaml[$this->pos];

            if ($c === '&') {
                return $this->parseAnchoredNode($indent, $nodeCol);
            }
            if ($c === '!') {
                return $this->parseTaggedNode($indent, $nodeCol);
            }
            if ($c === '*') {
                return $this->parseAliasNode();
            }
            if ($c === '[') {
                return $this->parseFlowCollectionNode($indent);
            }
            if ($c === '{') {
                return $this->parseFlowCollectionNode($indent);
            }
            if ($c === '|') {
                return $this->parseLiteralBlock($indent);
            }
            if ($c === '>') {
                return $this->parseFoldedBlock($indent);
            }
            if ($c === "'") {
                return $this->parseSingleQuotedScalarOrMapping($indent);
            }
            if ($c === '"') {
                return $this->parseDoubleQuotedScalarOrMapping($indent);
            }
            if ($c === '?') {
                $next = $this->pos + 1 < $this->len ? $this->yaml[$this->pos + 1] : '';
                if ($next === ' ' || $next === "\t" || $next === "\n" || $next === "\r" || $next === '') {
                    return $this->parseExplicitKeyBlock($indent);
                }
            }
            if ($c === '@' || $c === '`') {
                $column = $c === '@' ? 28 : 26;
                throw new LexerException("Cannot start plain scalar with '{$c}': Reserved indicator in line {$this->line}, column {$column}");
            }
            if ($c === '%' && $this->isAtLineStart()) {
                throw new ParserException("Directive cannot appear where a document node is expected at line {$this->line}");
            }

            return $this->parsePlainScalarOrCollection($indent);
        } finally {
            $this->depth--;
        }
    }

    private function parsePlainScalarOrCollection(int $indent): mixed
    {
        $c = $this->yaml[$this->pos];

        if ($c === ':' && $this->flowDepth === 0) {
            $afterColon = $this->pos + 1 < $this->len ? $this->yaml[$this->pos + 1] : '';
            if ($afterColon === ' ' || $afterColon === "\t" || $afterColon === "\n" || $afterColon === "\r" || $afterColon === '') {
                if ($this->captureMetadata) {
                    $this->pendingFirstKeyMetadataForMappingBody = new NodeMetadata(
                        line: $this->line,
                        column: $this->columnAt($this->pos),
                    );
                }
                $this->pos++;
                $this->skipSpaces();

                return $this->parseBlockMappingBody($indent, 'null');
            }
        }

        if ($c === ':' && $this->flowDepth > 0) {
            $afterColon = $this->pos + 1 < $this->len ? $this->yaml[$this->pos + 1] : '';
            if ($afterColon === ' ' || $afterColon === "\t" || $afterColon === "\n" || $afterColon === "\r" || $afterColon === '' || $afterColon === ',' || $afterColon === '}' || $afterColon === ']') {
                $this->pos++;
                $this->skipFlowWhitespace();
                $c2 = $this->yaml[$this->pos] ?? '';
                if ($c2 === ']' || $c2 === '}' || $c2 === ',') {
                    $value = null;
                } else {
                    $value = $this->parseNode($indent);
                }

                return ['null' => $value];
            }
        }

        if ($c === '-') {
            $next = $this->pos + 1 < $this->len ? $this->yaml[$this->pos + 1] : '';
            if ($next === ' ' || $next === "\t" || $next === "\n" || $next === "\r" || $next === '') {
                if ($this->flowDepth > 0) {
                    throw new ParserException("Dash cannot be used as a value in flow context at line {$this->line}");
                }
                $lineStart = strrpos(substr($this->yaml, 0, $this->pos), "\n");
                $actualCol = ($lineStart === false) ? $this->pos : ($this->pos - $lineStart - 1);

                return $this->parseBlockSequence($actualCol);
            }
        }

        $keyStartLineForMeta = $this->line;
        $keyStartColForMeta = $this->columnAt($this->pos);
        $scalar = $this->readPlainScalar($indent);

        if ($scalar === '' && $this->flowDepth === 0) {
            return null;
        }

        $this->skipSpaces();
        if ($this->pos < $this->len && $this->yaml[$this->pos] === ':') {
            $afterColon = $this->pos + 1 < $this->len ? $this->yaml[$this->pos + 1] : '';
            if ($afterColon === ' ' || $afterColon === "\t" || $afterColon === "\n" || $afterColon === "\r" || $afterColon === ''
                || ($this->flowDepth > 0 && ($afterColon === ',' || $afterColon === '}' || $afterColon === ']'))
            ) {
                if ($this->flowDepth > 0 && strlen($scalar) > 1000) {
                    $keyEndCol = $this->columnAt($this->pos - strlen($scalar) + 999);
                    throw new ParserException("Mapping keys cannot be longer than 1000 characters at line {$this->line}, column {$keyEndCol}");
                }

                if ($this->flowDepth === 0) {
                    if ($this->captureMetadata) {
                        $this->pendingFirstKeyMetadataForMappingBody = new NodeMetadata(
                            line: $keyStartLineForMeta,
                            column: $keyStartColForMeta,
                        );
                    }
                    $this->pos++;
                    $this->skipSpaces();

                    return $this->parseBlockMappingBody($indent, $scalar);
                }
                $this->pos++;
                $this->skipFlowWhitespace();
                $c2 = $this->yaml[$this->pos] ?? '';
                if ($c2 === '}' || $c2 === ',' || $c2 === ']') {
                    $value = null;
                } else {
                    $value = $this->parseNode($indent);
                }

                return [$scalar => $value];

            }
        }

        return $this->resolveScalar($scalar);
    }

    private function parseDirectiveLine(): void
    {
        $lineEnd = $this->pos + strcspn($this->yaml, "\n\r", $this->pos);
        $line = substr($this->yaml, $this->pos, $lineEnd - $this->pos);
        $directiveRecognized = false;

        if (preg_match('/\S#/', $line) === 1) {
            throw new LexerException("Invalid comment placement in directive at line {$this->line}, column 0");
        }
        $directivePart = preg_replace('/\s*#.*$/', '', $line);

        if (preg_match('/^%YAML\s+(\d+)\.(\d+)\s*$/i', $directivePart, $matches) === 1) {
            if ($this->yamlDirectiveSeen) {
                throw new LexerException("YAML directive already defined earlier in line {$this->line}, column 0");
            }
            $this->yamlDirectiveSeen = true;
            $directiveRecognized = true;
            $major = (int) $matches[1];
            $minor = (int) $matches[2];

            if ($major === 1 && $minor === 1) {
                $this->version = Version::VERSION_1_1;
            } elseif ($major === 1 && $minor === 2) {
                $this->version = Version::VERSION_1_2;
            }
        }
        if (!$directiveRecognized && preg_match('/^%TAG\s+(\S+)\s+(\S+)\s*$/i', $directivePart, $tagMatch) === 1) {
            $handle = $tagMatch[1];
            $prefix = $tagMatch[2];
            if (isset($this->tagHandlesDefined[$handle])) {
                throw new LexerException("TAG directive with handle '$handle' already defined earlier in line {$this->line}, column 0");
            }
            $this->tagHandlesDefined[$handle] = true;
            $this->tagHandles[$handle] = $prefix;
            $this->tagHandlesFreshForDocument = true;
            $directiveRecognized = true;
        }
        if (!$directiveRecognized) {
            if (preg_match('/^%(YAML|TAG)\s/i', $directivePart) === 1) {
                throw new LexerException("Invalid directive format in line {$this->line}, column 0");
            }
        }

        $this->pos = $lineEnd;
        $this->consumeNewline();
        $this->skipBlankLinesAndComments();
    }

    /**
     * Skip space characters. Tab characters cause an error when used for indentation.
     */
    private function skipSpaces(): void
    {
        $this->pos += strspn($this->yaml, " \t", $this->pos);
    }

    /**
     * Returns true when $this->pos is at the very start of a line (i.e. the
     * character immediately before pos is a newline, or pos is 0 / past end).
     * Used to avoid calling skipToEndOfLine() after parseNode() already advanced
     * past the current line while parsing a multi-line sub-tree.
     */
    private function isAtLineStart(): bool
    {
        return $this->pos === 0
            || $this->pos >= $this->len
            || $this->yaml[$this->pos - 1] === "\n"
            || $this->yaml[$this->pos - 1] === "\r";
    }

    /**
     * Skip whitespace including newlines (for flow context).
     */
    private function skipFlowWhitespace(): bool
    {
        $hadNewline = false;
        $atLineStart = true;

        while ($this->pos < $this->len) {
            $c = $this->yaml[$this->pos];
            if ($c === ' ') {
                $this->pos++;
                $atLineStart = false;
            } elseif ($c === "\t") {
                if ($atLineStart) {
                    $nextPos = $this->pos + 1;
                    while ($nextPos < $this->len && $this->yaml[$nextPos] === ' ') {
                        $nextPos++;
                    }

                    if ($nextPos < $this->len && $this->yaml[$nextPos] !== "\n" && $this->yaml[$nextPos] !== "\r" && $this->yaml[$nextPos] !== '#' &&
                        $this->yaml[$nextPos] !== ']' && $this->yaml[$nextPos] !== '}' && $this->yaml[$nextPos] !== ')' && $this->yaml[$nextPos] !== ',') {
                        throw new ParserException("Tabs cannot be used for indentation in flow context at line {$this->line}");
                    }
                }

                $this->pos++;
                if (!$atLineStart) {
                    $atLineStart = false;
                }
            } elseif ($c === "\n") {
                $this->pos++;
                $this->line++;
                $hadNewline = true;
                $atLineStart = true;
            } elseif ($c === "\r") {
                $this->pos++;
                if ($this->pos < $this->len && $this->yaml[$this->pos] === "\n") {
                    $this->pos++;
                }
                $this->line++;
                $hadNewline = true;
                $atLineStart = true;
            } elseif ($c === '#') {
                $this->skipToEndOfLine();
                $atLineStart = false;
            } else {
                break;
            }
        }

        return $hadNewline;
    }

    /**
     * Enforce that a flow collection's continuation lines (reached only
     * after crossing a line break within the collection) are indented
     * strictly more than the indentation of the enclosing block node.
     * A flow collection embedded in block context whose tokens regress to
     * the same or a shallower column than that context is ambiguous and
     * invalid per the YAML flow-in-block indentation rule. Closing
     * indicators are exempt: '}' / ']' commonly dedent back to (or past)
     * the opener's context to mark the end of the collection.
     */
    private function guardFlowContinuationIndent(bool $crossedNewline, int $rootIndent): void
    {
        if (!$crossedNewline || $this->pos >= $this->len) {
            return;
        }
        $c = $this->yaml[$this->pos];
        if ($c === '}' || $c === ']') {
            return;
        }
        if ($this->columnAt($this->pos) <= $rootIndent) {
            throw new ParserException("Wrong indented flow collection at line {$this->line}");
        }
    }

    /**
     * True when the current line has a mapping indicator before the cursor.
     */
    private function lineHasMappingIndicatorBeforeCursor(): bool
    {
        $lineStart = strrpos(substr($this->yaml, 0, $this->pos), "\n");
        $lineStart = $lineStart === false ? 0 : $lineStart + 1;
        $prefix = substr($this->yaml, $lineStart, $this->pos - $lineStart);

        return str_contains($prefix, ':');
    }

    /**
     * Advance past remaining content on the current line (up to but NOT including newline).
     */
    private function skipToEndOfLine(): void
    {
        $this->pos += strcspn($this->yaml, "\n\r", $this->pos);
    }

    /**
     * Skip inline comment if present (space(s) + # + rest of line). Does NOT consume newline.
     */
    private function skipInlineComment(): void
    {
        $spaces = strspn($this->yaml, ' ', $this->pos);
        if ($spaces > 0 && $this->pos + $spaces < $this->len && $this->yaml[$this->pos + $spaces] === '#') {
            $this->pos += $spaces;
            $this->skipToEndOfLine();
        }
    }

    /**
     * After a node has been parsed and we're not at the start of a line,
     * determine whether the remaining content on the line is a valid trailing
     * comment (preceded by whitespace or the start of the line, per spec) and
     * skip it, or otherwise reject it as unexpected trailing content. Any
     * whitespace already consumed by the preceding scalar reader still counts,
     * since the check looks at the character immediately before the '#'.
     */
    private function skipTrailingCommentOrFailOnContent(string $context): void
    {
        $this->pos += strspn($this->yaml, " \t", $this->pos);
        if ($this->pos >= $this->len || $this->yaml[$this->pos] === "\n" || $this->yaml[$this->pos] === "\r") {
            return;
        }
        $precedingChar = $this->pos > 0 ? $this->yaml[$this->pos - 1] : '';
        $precededByWhitespace = $precedingChar === ' ' || $precedingChar === "\t"
            || $precedingChar === "\n" || $precedingChar === "\r" || $this->pos === 0;
        if ($this->yaml[$this->pos] === '#' && $precededByWhitespace) {
            $this->skipToEndOfLine();

            return;
        }
        throw new ParserException("Unexpected content after {$context} at line {$this->line}");
    }

    /**
     * Consume one newline (\\n, \\r\\n, or \\r).
     */
    private function consumeNewline(): void
    {
        if ($this->pos >= $this->len) {
            return;
        }
        $c = $this->yaml[$this->pos];
        if ($c === "\r") {
            $this->pos++;
            if ($this->pos < $this->len && $this->yaml[$this->pos] === "\n") {
                $this->pos++;
            }
            $this->line++;
        } elseif ($c === "\n") {
            $this->pos++;
            $this->line++;
        }
    }

    /**
     * Skip lines that are blank (only spaces/tabs) or comment-only.
     * After this call, $this->pos is at the start of the first non-blank, non-comment line
     * (still pointing at any leading spaces).
     */
    private function skipBlankLinesAndComments(): void
    {
        while ($this->pos < $this->len) {
            $spaces = strspn($this->yaml, ' ', $this->pos);
            $p = $this->pos + $spaces;

            if ($p >= $this->len) {
                $this->pos = $p;

                return;
            }

            $c = $this->yaml[$p];

            if ($c === "\n") {
                $this->pos = $p + 1;
                $this->line++;
                continue;
            }
            if ($c === "\r") {
                $this->pos = $p + 1;
                if ($this->pos < $this->len && $this->yaml[$this->pos] === "\n") {
                    $this->pos++;
                }
                $this->line++;
                continue;
            }
            if ($c === '#') {
                $this->pos = $p;
                $this->skipToEndOfLine();
                continue;
            }
            if ($c === "\t") {
                $t = strspn($this->yaml, " \t", $this->pos);
                $p2 = $this->pos + $t;
                $c2 = $p2 < $this->len ? $this->yaml[$p2] : '';
                if ($c2 === "\n" || $c2 === "\r" || $c2 === '' || $c2 === '#') {
                    $this->pos = $p2;
                    if ($c2 === '#') {
                        $this->skipToEndOfLine();
                    }
                    continue;
                }
            }

            return;
        }
    }

    /**
     * Count (but do NOT advance past) leading spaces on current position.
     */
    private function countLineIndent(): int
    {
        if ($this->pos < $this->len && $this->yaml[$this->pos] === "\t") {
            throw new LexerException('Tab character found in block indentation.');
        }

        return strspn($this->yaml, ' ', $this->pos);
    }

    /**
     * Zero-based column of the given position within its line.
     */
    private function columnAt(int $pos): int
    {
        $lineStart = strrpos(substr($this->yaml, 0, $pos), "\n");

        return $lineStart === false ? $pos : $pos - $lineStart - 1;
    }

    /**
     * True when the ':' at $pos acts as a block mapping value indicator.
     */
    private function isBlockValueIndicator(int $pos): bool
    {
        if (($this->yaml[$pos] ?? '') !== ':') {
            return false;
        }

        $afterColon = $this->yaml[$pos + 1] ?? '';

        return $afterColon === ' ' || $afterColon === "\t" || $afterColon === "\n" || $afterColon === "\r" || $afterColon === '';
    }

    /**
     * Check if the 3 characters starting at $this->pos match the given marker.
     * Also checks that the character after is whitespace/EOF/# (not part of a word).
     */
    private function isDocMarker(string $marker): bool
    {
        if ($this->pos + 2 >= $this->len) {
            return false;
        }
        if ($this->yaml[$this->pos] !== $marker[0]
            || $this->yaml[$this->pos + 1] !== $marker[1]
            || $this->yaml[$this->pos + 2] !== $marker[2]
        ) {
            return false;
        }
        $after = $this->pos + 3 < $this->len ? $this->yaml[$this->pos + 3] : '';

        return $after === '' || $after === ' ' || $after === "\t" || $after === "\n" || $after === "\r" || $after === '#';
    }

    /**
     * Validate trailing content after a flow collection closes.
     * After ']' or '}', the next character must be EOF, newline, space, comment,
     * or a valid continuation character (comma, bracket, colon for mappings).
     */
    private function validateFlowCollectionTrailingContent(): void
    {
        if ($this->pos >= $this->len) {
            return;
        }

        $c = $this->yaml[$this->pos];

        if ($c === "\n" || $c === "\r"
            || $c === ',' || $c === ']' || $c === '}' || $c === ':') {
            return;
        }
        if ($c === '#') {
            $precedingChar = $this->pos > 0 ? $this->yaml[$this->pos - 1] : '';
            if ($this->pos === 0 || $precedingChar === ' ' || $precedingChar === "\t"
                || $precedingChar === "\n" || $precedingChar === "\r") {
                return;
            }
            throw new ParserException("Comment indicator '#' must be preceded by whitespace at line {$this->line}");
        }

        throw new ParserException("Unexpected trailing content after flow collection at line {$this->line}");
    }
}
