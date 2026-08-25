<?php

namespace MaxBeckers\YamlParser;

use MaxBeckers\YamlParser\Lexer\Lexer;
use MaxBeckers\YamlParser\Lexer\LexerContext;
use MaxBeckers\YamlParser\Lexer\TokenStream;
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
    private bool $preferPlainArrays;

    public function __construct(
        ?TagRegistry $tagRegistry = null,
        bool $preferPlainArrays = false,
    ) {
        $this->tagRegistry = $tagRegistry ?? new TagRegistry();
        $this->resolver = new Resolver($this->tagRegistry);
        $this->preferPlainArrays = $preferPlainArrays;

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
        return $this->parseWithArrayPreference($yaml, $stripWrapperOnSingleItem, $this->preferPlainArrays);
    }

    public function parsePlainArray(string $yaml, bool $stripWrapperOnSingleItem = true): mixed
    {
        return $this->parseWithArrayPreference($yaml, $stripWrapperOnSingleItem, true);
    }

    private function parseWithArrayPreference(string $yaml, bool $stripWrapperOnSingleItem, bool $preferPlainArrays): mixed
    {
        $tokens = Lexer::tokenize(new LexerContext($yaml));
        $ast = Parser::parse(new ParserContext(new TokenStream($tokens)));
        $ast = $this->resolver->resolve($ast);
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

        $contents = file_get_contents($filename);
        if ($contents === false) {
            throw new \RuntimeException("Unable to read file: {$filename}");
        }

        return $contents;
    }
}
