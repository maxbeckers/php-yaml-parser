<?php

namespace MaxBeckers\YamlParser\Exception;

use MaxBeckers\YamlParser\Api\TokenInterface;

class ParserException extends YamlParserException
{
    public function __construct(
        string $message,
        public readonly ?TokenInterface $token = null,
        ?\Throwable $previous = null
    ) {
        $fullMessage = $message;

        if ($token !== null) {
            $fullMessage .= " at line {$token->line}, column {$token->column}";
        }

        parent::__construct($fullMessage, 0, $previous);
    }
}
