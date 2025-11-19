<?php

namespace MaxBeckers\YamlParser\Rule\Flow;

enum FlowType
{
    case SEQUENCE_START;
    case SEQUENCE_END;
    case MAPPING_START;
    case MAPPING_END;
    case ENTRY_SEPARATOR;
}
