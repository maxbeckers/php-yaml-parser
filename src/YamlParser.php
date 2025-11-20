<?php

namespace MaxBeckers\YamlParser;

use MaxBeckers\YamlParser\Lexer\Lexer;
use MaxBeckers\YamlParser\Lexer\LexerContext;
use MaxBeckers\YamlParser\Lexer\TokenStream;
use MaxBeckers\YamlParser\Parser\Parser;
use MaxBeckers\YamlParser\Parser\ParserContext;
use MaxBeckers\YamlParser\Resolver\Anchor\AnchorResolver;
use MaxBeckers\YamlParser\Resolver\Merge\MergeResolver;
use MaxBeckers\YamlParser\Resolver\Tag\Basic\BinaryTagHandler;
use MaxBeckers\YamlParser\Resolver\Tag\Basic\BoolTagHandler;
use MaxBeckers\YamlParser\Resolver\Tag\Basic\FloatTagHandler;
use MaxBeckers\YamlParser\Resolver\Tag\Basic\IntTagHandler;
use MaxBeckers\YamlParser\Resolver\Tag\Basic\NullTagHandler;
use MaxBeckers\YamlParser\Resolver\Tag\Basic\StringTagHandler;
use MaxBeckers\YamlParser\Resolver\Tag\Basic\TimestampTagHandler;
use MaxBeckers\YamlParser\Resolver\Tag\TagProcessor;
use MaxBeckers\YamlParser\Resolver\Tag\TagRegistry;
use MaxBeckers\YamlParser\Serializer\ArrayObjectSerializer;

final class YamlParser
{
    private TagProcessor $tagProcessor;
    private TagRegistry $tagRegistry;

    public function __construct(
        ?TagRegistry $tagRegistry = null,
    ) {
        $this->tagRegistry = $tagRegistry ?? new TagRegistry();
        $this->tagProcessor = new TagProcessor($this->tagRegistry);

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
        $tokens = Lexer::tokenize(new LexerContext($yaml));
        $ast = Parser::parse(new ParserContext(new TokenStream($tokens)));
        $ast = $this->tagProcessor->process($ast);
        $ast = AnchorResolver::resolve($ast);
        $ast = MergeResolver::resolve($ast);
        $serializer = new ArrayObjectSerializer();

        $serialized = $serializer->serialize($ast);
        if ($stripWrapperOnSingleItem && ($serialized instanceof \ArrayObject || is_array($serialized)) && count($serialized) === 1) {
            return $serialized[0];
        }

        return $serialized;
    }

    public function parseFile(string $filename, bool $stripWrapperOnSingleItem = false): mixed
    {
        if (!file_exists($filename)) {
            throw new \InvalidArgumentException("File not found: {$filename}");
        }

        $yaml = file_get_contents($filename);

        return $this->parse($yaml, $stripWrapperOnSingleItem);
    }

    public function getTagRegistry(): TagRegistry
    {
        return $this->tagRegistry;
    }
}
