<?php

namespace MaxBeckers\YamlParser\Lexer;

enum ContextMode
{
    case STREAM_START;
    case DOCUMENT_START;
    case BLOCK_KEY;
    case BLOCK_VALUE;
    case BLOCK_SEQUENCE_ENTRY;
    case FLOW_SEQUENCE;
    case FLOW_MAPPING_KEY;
    case FLOW_MAPPING_VALUE;
    case BLOCK_SCALAR_CONTENT;
    case EXPLICIT_KEY;
}
