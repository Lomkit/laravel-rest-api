<?php

namespace Lomkit\Rest\Authorization;

final readonly class DecisionKey
{
    private const string PREFIX = 'rest.authorizations.';

    private function __construct(private string $check) {}

    public static function of(Check $check): self
    {
        return new self($check->key());
    }

    public function for(UserIdentity $user): string
    {
        return self::PREFIX.hash('sha256', $user->key().'|'.$this->check);
    }
}
