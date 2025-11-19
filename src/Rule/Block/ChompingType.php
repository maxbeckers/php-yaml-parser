<?php

namespace MaxBeckers\YamlParser\Rule\Block;

enum ChompingType
{
    case STRIP;
    case CLIP;
    case KEEP;
}
