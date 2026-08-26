<?php

namespace MaxBeckers\YamlParser;

use MaxBeckers\YamlParser\Api\NodeInterface;
use MaxBeckers\YamlParser\Config\ParsingConfig;
use MaxBeckers\YamlParser\Lexer\Lexer;
use MaxBeckers\YamlParser\Lexer\LexerContext;
use MaxBeckers\YamlParser\Lexer\TokenStream;
use MaxBeckers\YamlParser\Metadata\MetadataProvider;
use MaxBeckers\YamlParser\Node\DocumentNode;
use MaxBeckers\YamlParser\Node\MappingNode;
use MaxBeckers\YamlParser\Node\NodeMetadata;
use MaxBeckers\YamlParser\Node\SequenceNode;
use MaxBeckers\YamlParser\Node\YamlNode;
use MaxBeckers\YamlParser\Parser\Parser;
use MaxBeckers\YamlParser\Parser\ParserContext;
use MaxBeckers\YamlParser\Resolver\Resolver;
use MaxBeckers\YamlParser\Resolver\Tag\Basic\BinaryTagHandler;
use MaxBeckers\YamlParser\Resolver\Tag\Basic\BoolTagHandler;
use MaxBeckers\YamlParser\Resolver\Tag\Basic\FloatTagHandler;
use MaxBeckers\YamlParser\Resolver\Tag\Basic\IntTagHandler;
use MaxBeckers\YamlParser\Resolver\Tag\Basic\NullTagHandler;
use MaxBeckers\YamlParser\Resolver\Tag\Basic\StringTagHandler;
use MaxBeckers\YamlParser\Resolver\Tag\Basic\TimestampTagHandler;
use MaxBeckers\YamlParser\Constructor\ArrayObjectConstructor;
use MaxBeckers\YamlParser\Resolver\Tag\TagRegistry;

final class YamlParser
{
    private Resolver $resolver;
    private TagRegistry $tagRegistry;
    private ParsingConfig $config;
    private ?NodeInterface $lastAst = null;

    public function __construct(
        ?TagRegistry $tagRegistry = null,
        bool $preferPlainArrays = false,
        ?ParsingConfig $config = null,
    ) {
        $this->tagRegistry = $tagRegistry ?? new TagRegistry();
        $this->resolver = new Resolver($this->tagRegistry);
        $this->config = $config ?? new ParsingConfig(returnPlainArrays: $preferPlainArrays);

        $this->tagRegistry->register(new BinaryTagHandler());
        $this->tagRegistry->register(new BoolTagHandler());
        $this->tagRegistry->register(new FloatTagHandler());
        $this->tagRegistry->register(new IntTagHandler());
        $this->tagRegistry->register(new NullTagHandler());
        $this->tagRegistry->register(new StringTagHandler());
        $this->tagRegistry->register(new TimestampTagHandler());
    }

    public function parse(string $yaml, bool $stripWrapperOnSingleItem = true): mixed
    {
        return $this->parseWithArrayPreference($yaml, $stripWrapperOnSingleItem, $this->config->returnPlainArrays);
    }

    public function parsePlainArray(string $yaml, bool $stripWrapperOnSingleItem = true): mixed
    {
        return $this->parseWithArrayPreference($yaml, $stripWrapperOnSingleItem, true);
    }

    private function parseWithArrayPreference(string $yaml, bool $stripWrapperOnSingleItem, bool $preferPlainArrays): mixed
    {
        $tokens = Lexer::tokenize(new LexerContext($yaml, trackTokenStartPositions: $this->config->preserveMetadata));
        $parserContext = new ParserContext(
            new TokenStream($tokens),
            strictMode: $this->config->strictMode,
            maxDepth: $this->config->maxDepth,
            preserveMetadata: $this->config->preserveMetadata,
        );
        $ast = Parser::parse($parserContext);

        if (!$this->config->lazyResolution || $this->astNeedsResolution($ast)) {
            $ast = $this->resolver->resolve($ast, $this->config->maxDepth);
        }

        $this->lastAst = $this->config->preserveMetadata ? $ast : null;
        $constructor = new ArrayObjectConstructor();

        $serialized = $constructor->construct($ast, preferPlainArrays: $preferPlainArrays);
        if ($stripWrapperOnSingleItem && ($serialized instanceof \ArrayObject || is_array($serialized)) && count($serialized) === 1) {
            return $serialized[0];
        }

        return $serialized;
    }

    public function parseFile(string $filename, bool $stripWrapperOnSingleItem = false): mixed
    {
        $yaml = $this->getFileContents($filename);

        return $this->parse($yaml, $stripWrapperOnSingleItem);
    }

    public function parseFilePlainArray(string $filename, bool $stripWrapperOnSingleItem = false): mixed
    {
        $yaml = $this->getFileContents($filename);

        return $this->parsePlainArray($yaml, $stripWrapperOnSingleItem);
    }

    private function getFileContents(string $filename): string
    {
        if (!file_exists($filename)) {
            throw new \InvalidArgumentException("File not found: {$filename}");
        }

        if ($this->config->maxFileSize !== null) {
            $fileSize = filesize($filename);
            if ($fileSize === false) {
                throw new \RuntimeException("Unable to determine file size: {$filename}");
            }

            if ($fileSize > $this->config->maxFileSize) {
                throw new \RuntimeException("File size {$fileSize} exceeds configured limit of {$this->config->maxFileSize} bytes");
            }
        }

        $contents = file_get_contents($filename);
        if ($contents === false) {
            throw new \RuntimeException("Unable to read file: {$filename}");
        }

        return $contents;
    }

    public function getLastAst(): ?NodeInterface
    {
        return $this->lastAst;
    }

    public function getMetadataProvider(bool $stripWrapperOnSingleItem = true): MetadataProvider
    {
        if ($this->lastAst === null) {
            throw new \LogicException('Metadata is not available. Enable ParsingConfig::preserveMetadata and parse before requesting metadata.');
        }

        return new MetadataProvider($this->lastAst, $stripWrapperOnSingleItem);
    }

    public function getMetadataForPath(array|string|int|null $path = [], bool $stripWrapperOnSingleItem = true): ?NodeMetadata
    {
        return $this->getMetadataProvider($stripWrapperOnSingleItem)->getMetadata($path);
    }

    public function getKeyMetadataForPath(array|string|int|null $path, bool $stripWrapperOnSingleItem = true): ?NodeMetadata
    {
        return $this->getMetadataProvider($stripWrapperOnSingleItem)->getKeyMetadata($path);
    }

    private function astNeedsResolution(NodeInterface $node): bool
    {
        $metadata = $node->getMetadata();
        if ($metadata->getTag() !== null || $metadata->getAnchor() !== null || $metadata->getAlias() !== null || $metadata->isMergeKey()) {
            return true;
        }

        return match (true) {
            $node instanceof YamlNode => $this->documentsNeedResolution($node),
            $node instanceof DocumentNode => $this->astNeedsResolution($node->getRoot()),
            $node instanceof SequenceNode => $this->sequenceNeedsResolution($node),
            $node instanceof MappingNode => $this->mappingNeedsResolution($node),
            default => false,
        };
    }

    private function documentsNeedResolution(YamlNode $node): bool
    {
        foreach ($node->getDocuments() as $document) {
            if ($this->astNeedsResolution($document)) {
                return true;
            }
        }

        return false;
    }

    private function sequenceNeedsResolution(SequenceNode $node): bool
    {
        foreach ($node->getItems() as $item) {
            if ($this->astNeedsResolution($item)) {
                return true;
            }
        }

        return false;
    }

    private function mappingNeedsResolution(MappingNode $node): bool
    {
        foreach ($node->getMappingNodeItems() as $item) {
            if ($this->astNeedsResolution($item->getKey()) || $this->astNeedsResolution($item->getValue())) {
                return true;
            }
        }

        return false;
    }
}
