<?php

namespace MaxBeckers\YamlParser\Lexer;

enum TokenType: string
{
    // Structural
    case INDENT = 'INDENT';
    case DEDENT = 'DEDENT';
    case NEWLINE = 'NEWLINE';
    case EOF = 'EOF';

    // Indicators
    case KEY_INDICATOR = 'KEY_INDICATOR';
    case SEQUENCE_INDICATOR = 'SEQUENCE_INDICATOR';
    case MAPPING_START = 'MAPPING_START';
    case MAPPING_END = 'MAPPING_END';
    case SEQUENCE_START = 'SEQUENCE_START';
    case SEQUENCE_END = 'SEQUENCE_END';
    case FLOW_SEPARATOR = 'FLOW_SEPARATOR';
    case EXPLICIT_KEY = 'EXPLICIT_KEY';
    case DOCUMENT_START = 'DOCUMENT_START';
    case DOCUMENT_END = 'DOCUMENT_END';

    // Anchors & Aliases
    case ANCHOR = 'ANCHOR';
    case ALIAS = 'ALIAS';
    case TAG = 'TAG';

    // Scalars
    case PLAIN_SCALAR = 'PLAIN_SCALAR';
    case SINGLE_QUOTED_SCALAR = 'SINGLE_QUOTED_SCALAR';
    case DOUBLE_QUOTED_SCALAR = 'DOUBLE_QUOTED_SCALAR';
    case LITERAL_SCALAR = 'LITERAL_SCALAR';
    case FOLDED_SCALAR = 'FOLDED_SCALAR';

    // Comments
    case COMMENT = 'COMMENT';

    // Directives
    case DIRECTIVE = 'DIRECTIVE';
}
