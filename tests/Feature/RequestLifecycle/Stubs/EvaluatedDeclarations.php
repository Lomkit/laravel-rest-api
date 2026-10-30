<?php

namespace Lomkit\Rest\Tests\Feature\RequestLifecycle\Stubs;

use Lomkit\Rest\Http\Requests\RestRequest;

final class EvaluatedDeclarations
{
    /** @var list<array{string, ?string}> */
    private array $evaluations = [];

    public function record(string $declaration, RestRequest $request): void
    {
        $this->evaluations[] = [$declaration, $request->user()?->getAttribute('name')];
    }

    /** @return array<string, int> */
    public function counts(): array
    {
        $counts = array_count_values(array_column($this->evaluations, 0));
        ksort($counts);

        return $counts;
    }

    /** @return list<?string> */
    public function usersOf(string $declaration): array
    {
        $users = [];

        foreach ($this->evaluations as [$evaluated, $user]) {
            if ($evaluated === $declaration) {
                $users[] = $user;
            }
        }

        return $users;
    }
}
