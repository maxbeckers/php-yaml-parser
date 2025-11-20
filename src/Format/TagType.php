<?php

namespace MaxBeckers\YamlParser\Format;

enum TagType
{
    case VERBATIM;
    case SHORTHAND;
    case NON_SPECIFIC;
    case NAMED;
}
