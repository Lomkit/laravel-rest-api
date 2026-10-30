<?php

namespace Lomkit\Rest\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Lomkit\Rest\Authorization\Denial;

final class AuthorizationDenied extends AuthorizationException
{
    private const int LIMIT = 50;

    /** @var non-empty-list<Denial> */
    public readonly array $denials;

    /** @param non-empty-list<Denial> $denials */
    public function __construct(array $denials)
    {
        $this->denials = array_slice($denials, 0, self::LIMIT);
        $decision = $denials[0]->decision;

        parent::__construct($decision->message, $decision->code);

        $this->setResponse($decision->toResponse());
        $this->withStatus($decision->status);
    }

    /** @return list<array{path: string, ability: string, key?: int|string, message: string}> */
    public function toArray(): array
    {
        return array_map(fn (Denial $denial): array => $denial->toArray(), $this->denials);
    }
}
