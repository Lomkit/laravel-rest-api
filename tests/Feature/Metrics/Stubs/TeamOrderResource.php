<?php

namespace Lomkit\Rest\Tests\Feature\Metrics\Stubs;

use Illuminate\Contracts\Database\Eloquent\Builder;

class TeamOrderResource extends MetricOrderResource
{
    #[\Override]
    public function perimeterQuery(Builder $query): Builder
    {
        return $query->where('orders.team_id', $this->team());
    }

    #[\Override]
    public function authorizationCacheContext(): array
    {
        return ['team' => $this->team()];
    }

    private function team(): ?int
    {
        $team = $this->request()->user()?->team_id;

        return is_int($team) ? $team : null;
    }
}
