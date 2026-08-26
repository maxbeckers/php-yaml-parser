<?php

namespace MaxBeckers\YamlParser\Service;

use MaxBeckers\YamlParser\Metadata\MetadataProvider;
use MaxBeckers\YamlParser\Metadata\ValueWithMetadata;
use MaxBeckers\YamlParser\Node\NodeMetadata;

final class ErrorReporter
{
    public function format(string $message, ?NodeMetadata $metadata = null): string
    {
        if ($metadata === null) {
            return $message;
        }

        $line = $metadata->getLine();
        $column = $metadata->getColumn();

        if ($line === null && $column === null) {
            return $message;
        }

        if ($line !== null && $column !== null) {
            return sprintf('%s at line %d, column %d', $message, $line, $column);
        }

        if ($line !== null) {
            return sprintf('%s at line %d', $message, $line);
        }

        return sprintf('%s at column %d', $message, $column);
    }

    public function formatForPath(string $message, MetadataProvider $provider, array|string|int|null $path = []): string
    {
        return $this->format($message, $provider->getMetadata($path));
    }

    public function formatForValue(string $message, ValueWithMetadata $valueWithMetadata): string
    {
        return $this->format($message, $valueWithMetadata->getMetadata());
    }
}
