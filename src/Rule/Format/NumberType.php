<?php

namespace MaxBeckers\YamlParser\Rule\Format;

enum NumberType
{
    case INTEGER;
    case FLOAT;
    case OCTAL;
    case HEXADECIMAL;
}
