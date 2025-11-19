<?php

namespace MaxBeckers\YamlParser\Rule;

enum RuleResultType
{
    case ALLOWED;
    case BREAKS;
    case FORBIDDEN;
}
