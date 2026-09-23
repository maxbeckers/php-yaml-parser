# YAML Parser Library

A PHP YAML parser built for learning and understanding the YAML 1.2 specification.

## Features

- **Tag support**: Parse standard and application-specific YAML tags.
- **Anchor and alias support**: Resolve anchors and aliases automatically.
- **Error handling**: Provide detailed messages with line and column information.
- **Merge key support**: Support merge keys for mappings, although they are not part of YAML 1.2.

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

### Plain array output

Basic parsing returns `\ArrayObject` instances instead of arrays. With
`parsePlainArray()`, `\ArrayObject` is used only for recursive structures that
require reference handling.

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

Available options are `strictMode`, `returnPlainArrays`, `maxDepth`, `maxFileSize`,
and `preserveMetadata`.

### Custom Tag Handlers

Application-specific tags can transform scalar or collection values during
scanning:

```php
use MaxBeckers\YamlParser\Tag\CustomTagHandler;

$yamlParser->getTagRegistry()->register(
    new CustomTagHandler(
        '!env',
        static fn (mixed $value): string => getenv((string) $value) ?: (string) $value,
    ),
);

$data = $yamlParser->parse('database_host: !env DATABASE_HOST');
```

Handlers may accept a second `NodeMetadataInterface` argument to inspect the tag
and its source line and column. `%TAG` shorthand can be handled by registering
the expanded tag URI.

### Metadata and Diagnostics

Enable `preserveMetadata` to query line, column, tag, anchor, and alias data
after parsing:

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

## Contributing

Contributions are welcome. Feel free to submit issues or pull requests.

## Acknowledgments

Developed with reference to the [YAML 1.2.2 Specification](https://yaml.org/spec/1.2.2/).

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

---

**Questions or Issues?** Please open an issue on [GitHub](https://github.com/maxbeckers/php-yaml-parser/issues).

---

**Built with ❤️ for PHP developers**
