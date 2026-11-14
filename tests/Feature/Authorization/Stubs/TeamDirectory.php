<?php

namespace Lomkit\Rest\Tests\Feature\Authorization\Stubs;

use Illuminate\Contracts\Auth\Authenticatable;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Team;

class TeamDirectory
{
    /** @var array<string, array<int, string>> */
    private array $names = [];

    /** @param list<int> $teams */
    public function remember(?Authenticatable $user, array $teams): void
    {
        $names = Team::query()->whereKey($teams)->pluck('name', 'id')->all();

        $this->names[self::userKey($user)] = array_replace($this->names[self::userKey($user)] ?? [], $names);
    }

    public function name(?Authenticatable $user, int $team): ?string
    {
        return $this->names[self::userKey($user)][$team] ?? Team::query()->whereKey($team)->value('name');
    }

    private static function userKey(?Authenticatable $user): string
    {
        $key = $user?->getAuthIdentifier();

        return is_scalar($key) ? (string) $key : 'guest';
    }
}
