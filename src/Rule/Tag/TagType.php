<?php

namespace MaxBeckers\YamlParser\Rule\Tag;

enum TagType
{
    case VERBATIM;
    case SHORTHAND;
    case NON_SPECIFIC;
    case NAMED;
}
