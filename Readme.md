# YAML Parser Library

A PHP implementation of a YAML parser built for learning and understanding the YAML 1.2 specification.

## Features

- **YAML Support**: Support for the YAML 1.2 spec (not 100% supported)
  - Includes merge key support (even though not defined in YAML 1.2 spec)
- **Extensible Tag System**: Define and register custom tags with ease
- **Anchor and Alias Support**: Automatic resolution of anchors and aliases
- **Error Handling**: Detailed error messages with line and column information

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

## Architecture

The parser follows a multi-stage pipeline:

```plaintext
Input (string/file)
    ↓
Lexer (tokenization)
    ↓
Parser (AST building)
    ↓
Tag Processor (allows also custom tag handling)
    ↓
Resolver (anchors/aliases)
    ↓
Resolver (merge keys)
    ↓
Serializer (to PHP ArrayObject)
```

## Background

I have written this YAML parser library in PHP to learn more about YAML.
Already a lot of months ago someone said to me "YAML is a weird beast", that triggered my curiosity to learn more about it.
So i started to read the YAML specification and implement a parser for it.
While implementing the parser i found out that YAML is indeed a weird beast.
There are many edge cases and special cases that make it hard to implement a fully compliant parser.
Also the specification itself is not always clear and sometimes even contradictory.
So i had to make some decisions on how to handle certain cases.
I tried to follow the specification as closely as possible, but there are some cases where i had to deviate from it.
For example, the specification does not define how to handle merge keys in YAML 1.2, but i decided to implement it anyway, because it is a useful feature.
This means that this library is not fully compliant with the YAML specification and probably never will be 100% compliant.
Use at your own risk.
But i make it publicly available in the hope that it might be useful to someone.

## Todos

- [ ] Performance optimizations for large YAML files
- [ ] Code cleanup and refactoring
- [ ] Additional unit tests for edge cases
- [ ] Implement parsing for currently skipped test cases
- [ ] Optimize handling for special cases
- [ ] Add configurable options for parsing behavior
- [ ] Improve metadata handling for mappings and sequences

## Contributing

Contributions are welcome! Please feel free to submit issues or pull requests.

## Acknowledgments

Built with reference to the [YAML 1.2.2 Specification](https://yaml.org/spec/1.2.2/).
