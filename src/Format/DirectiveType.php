<?php

namespace MaxBeckers\YamlParser\Format;

enum DirectiveType: string
{
    case TAG = 'TAG';
    case YAML = 'YAML';
}
