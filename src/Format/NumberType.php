<?php

namespace MaxBeckers\YamlParser\Format;

enum NumberType
{
    case INTEGER;
    case FLOAT;
    case OCTAL;
    case HEXADECIMAL;
}
