# YAML Parser Library

A PHP implementation of a YAML parser built for learning and understanding the YAML 1.2 specification.

## Features

- **Extensible Tag System**: Define and register custom tags with ease
- **Anchor and Alias Support**: Automatic resolution of anchors and aliases
- **Error Handling**: Detailed error messages with line and column information
- **Merge Key Support**: Implements merge keys for mappings even though not defined in YAML 1.2

## Installation

```bash
composer require maxbeckers/php-yaml-parser
```

## Usage

### Basic Parsing

```php
use MaxBeckers\YamlParser\YamlParser;

$yamlParser = new YamlParser();
$data = $yamlParser->parseFile('config.yaml');
```

### Plain Array Output (Hybrid)

```php
use MaxBeckers\YamlParser\YamlParser;

$yamlParser = new YamlParser();

// Prefer plain arrays for normal structures.
// Circular reference paths are promoted to ArrayObject automatically.
$data = $yamlParser->parsePlainArray($yamlContent);
```

### Parsing Configuration

```php
use MaxBeckers\YamlParser\Config\ParsingConfig;
use MaxBeckers\YamlParser\YamlParser;

$yamlParser = new YamlParser(config: new ParsingConfig(
    strictMode: true,
    returnPlainArrays: true,
    maxDepth: 64,
    maxFileSize: 10 * 1024 * 1024,
    preserveMetadata: true,
));
$data = $yamlParser->parseFile('config.yaml');
```

Available options: `strictMode`, `returnPlainArrays`, `maxDepth`, `maxFileSize`, `lazyResolution`, and `preserveMetadata`.
Use strict mode for spec-focused validation, plain arrays for lighter output, and depth/file-size limits for safer untrusted input.

### Custom Tag Handlers

```php
// Register custom tag handler for environment variables
$yamlParser->getTagRegistry()->register(
    new CustomTagHandler('!env', function($value) {
        return getenv($value) ?: $value;
    })
);

// Use in YAML
// database_host: !env DATABASE_HOST
```

### Metadata Access

```php
use MaxBeckers\YamlParser\Config\ParsingConfig;
use MaxBeckers\YamlParser\Service\ErrorReporter;
use MaxBeckers\YamlParser\YamlParser;

$yaml = <<<'YAML'
name: Jane
items:
  - one
YAML;

$parser = new YamlParser(config: new ParsingConfig(preserveMetadata: true));
$result = $parser->parse($yaml);

$provider = $parser->getMetadataProvider();
$valueMetadata = $provider->getMetadata('items.0');      // value node metadata
$keyMetadata = $provider->getKeyMetadata('name');        // key node metadata
$wrapped = $provider->getValueWithMetadata($result, 'items.0');

$reporter = new ErrorReporter();
echo $reporter->formatForPath('Invalid list item', $provider, 'items.0');
// Invalid list item at line 3, column 4
```

Notes:
- Metadata access requires `preserveMetadata: true`.
- By default, path lookup follows the parser's single-document unwrapped output.
- If you parse with `stripWrapperOnSingleItem: false`, use `getMetadataProvider(stripWrapperOnSingleItem: false)`.

## Architecture

The library follows a multi-stage pipeline:

```plaintext
Input (string/file)
    ↓
Lexer (tokenization)
    ↓
Parser (AST building, implicit tag resolution)
    ↓
Resolver (explicit tags, anchors/aliases, merge keys)
    ↓
Constructor (to PHP ArrayObject / array)
```

### Performance Tip for Large Files

When parsing very large YAML files, disabling Xdebug can noticeably reduce runtime and memory overhead.

```bash
php -d xdebug.mode=off your-script.php
```

If you use Docker or CI, apply the same idea there (disable Xdebug for parse-heavy jobs).

## Background

This parser started as a YAML learning project and has since grown into a robust YAML library.
It now includes a configurable parsing pipeline, metadata preservation, resolver support for common YAML features, and broad PHPUnit coverage.
The focus is correctness, clear extension points, and practical behavior for both application code and tooling use cases.

## Contributing

Contributions are welcome! Please feel free to submit issues or pull requests.

## Acknowledgments

Built with reference to the [YAML 1.2.2 Specification](https://yaml.org/spec/1.2.2/).
