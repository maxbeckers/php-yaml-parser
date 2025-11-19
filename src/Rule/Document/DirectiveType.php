<?php

namespace MaxBeckers\YamlParser\Rule\Document;

enum DirectiveType: string
{
    case TAG = 'TAG';
    case YAML = 'YAML';
}
