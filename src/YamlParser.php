<?php

namespace MaxBeckers\YamlParser;

use MaxBeckers\YamlParser\Config\ParsingConfig;
use MaxBeckers\YamlParser\Parser\ScanParser;
use MaxBeckers\YamlParser\Metadata\MetadataProvider;
use MaxBeckers\YamlParser\Tag\TagRegistry;

final class YamlParser
{
    private ParsingConfig $config;
    private TagRegistry $tagRegistry;
    private ?array $lastMetadataTrees = null;

    public function __construct(
        bool $preferPlainArrays = false,
        ?ParsingConfig $config = null,
        ?TagRegistry $tagRegistry = null,
    ) {
        $this->config = $config ?? new ParsingConfig(returnPlainArrays: $preferPlainArrays);
        $this->tagRegistry = $tagRegistry ?? new TagRegistry();
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
        $wantsPlainArrays = $this->config->returnPlainArrays || $preferPlainArrays;
        $scanParser = new ScanParser($yaml, maxDepth: $this->config->maxDepth, strictMode: $this->config->strictMode, preferPlainArrays: $wantsPlainArrays, captureMetadata: $this->config->preserveMetadata, tagRegistry: $this->tagRegistry);
        $serialized = $scanParser->parseDocuments();

        if ($this->config->preserveMetadata) {
            $this->lastMetadataTrees = $scanParser->getDocumentMetadataTrees();
        }

        if ($wantsPlainArrays && $scanParser->hasAnchorsOrAliases()) {
            $serialized = $this->toPlainArray($serialized);
        }
        $serialized = $this->unwrapSingleDocumentIfNeeded($serialized, $stripWrapperOnSingleItem);

        return $wantsPlainArrays ? $serialized : $this->wrapArrayResult($serialized);
    }

    private function unwrapSingleDocumentIfNeeded(mixed $serialized, bool $stripWrapperOnSingleItem): mixed
    {
        if ($stripWrapperOnSingleItem && ($serialized instanceof \ArrayObject || is_array($serialized)) && count($serialized) === 1) {
            return $serialized[0];
        }

        return $serialized;
    }

    private function wrapArrayResult(mixed $serialized): mixed
    {
        if (is_array($serialized)) {
            return new \ArrayObject($serialized);
        }

        return $serialized;
    }

    private function toPlainArray(mixed $value): mixed
    {
        $cyclic = [];
        $stack = [];
        $visited = [];
        $this->collectCyclicNodes($value, $stack, $cyclic, $visited);

        return $this->convertToPlainArray($value, $cyclic);
    }

    /**
     * Collect the object ids of every ArrayObject that takes part in a reference
     * cycle. Those nodes must stay ArrayObject instances so the cycle survives
     * the conversion to plain arrays.
     *
     * @param list<int> $stack
     * @param array<int, true> $cyclic
     * @param array<int, true> $visited
     */
    private function collectCyclicNodes(mixed $value, array &$stack, array &$cyclic, array &$visited): void
    {
        if ($value instanceof \ArrayObject) {
            $objectId = spl_object_id($value);
            $stackPos = array_search($objectId, $stack, true);
            if ($stackPos !== false) {
                for ($i = $stackPos, $count = count($stack); $i < $count; $i++) {
                    $cyclic[$stack[$i]] = true;
                }

                return;
            }

            if (isset($visited[$objectId])) {
                return;
            }

            $visited[$objectId] = true;
            $stack[] = $objectId;
            foreach ($value as $item) {
                $this->collectCyclicNodes($item, $stack, $cyclic, $visited);
            }
            array_pop($stack);

            return;
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                $this->collectCyclicNodes($item, $stack, $cyclic, $visited);
            }
        }
    }

    /** @param array<int, true> $cyclic */
    private function convertToPlainArray(mixed $value, array $cyclic): mixed
    {
        if ($value instanceof \ArrayObject) {
            if (isset($cyclic[spl_object_id($value)])) {
                return $value;
            }

            $result = [];
            foreach ($value as $key => $item) {
                $result[$key] = $this->convertToPlainArray($item, $cyclic);
            }

            return $result;
        }

        if (is_array($value)) {
            $result = [];
            foreach ($value as $key => $item) {
                $result[$key] = $this->convertToPlainArray($item, $cyclic);
            }

            return $result;
        }

        return $value;
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

    public function getMetadataProvider(bool $stripWrapperOnSingleItem = true): MetadataProvider
    {
        if ($this->lastMetadataTrees === null) {
            throw new \LogicException('Metadata is not available. Enable ParsingConfig::preserveMetadata and parse before requesting metadata.');
        }

        return new MetadataProvider($this->lastMetadataTrees, $stripWrapperOnSingleItem);
    }

    public function getTagRegistry(): TagRegistry
    {
        return $this->tagRegistry;
    }
}
