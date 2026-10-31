<?php

namespace Lomkit\Rest\Text;

enum TermPattern: string
{
    case Alphanumeric = '/[\p{L}\p{M}\p{N}]+/u';
    case Underscored = '/[\p{L}\p{M}\p{N}]+(?:_+[\p{L}\p{M}\p{N}]+)*/u';

    /** @return list<string> */
    public function terms(string $value): array
    {
        return preg_match_all($this->value, $value, $matches) === false ? [] : $matches[0];
    }
}
