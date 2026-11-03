<?php

namespace Lomkit\Rest\Http\Serialization;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Search\GateAbility;
use Lomkit\Rest\Search\Plan\QueryPlan;
use stdClass;

final readonly class GateTable
{
    /** @param list<GateAbility>|null $abilities */
    private function __construct(
        private Resource $resource,
        private ?array $abilities,
    ) {}

    public static function of(QueryPlan $plan): self
    {
        return new self($plan->resource, $plan->resource->isGatingEnabled() ? $plan->gates : null);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function addTo(array $row, Model $model): array
    {
        if ($this->abilities === null) {
            return $row;
        }

        $row[$this->resource->context()->config()->gatesKey] = $this->answers($model, false);

        return $row;
    }

    /** @return array<string, stdClass> */
    public function meta(): array
    {
        $classLevel = array_filter($this->abilities ?? [], static fn (GateAbility $gate): bool => $gate->isClassLevel());

        if ($classLevel === []) {
            return [];
        }

        return [$this->resource->context()->config()->gatesKey => $this->answers($this->resource::newModel()::class, true)];
    }

    private function answers(Model|string $subject, bool $classLevel): stdClass
    {
        $names = $this->resource->context()->config()->gateNames;
        $answers = [];

        foreach ($this->abilities ?? [] as $gate) {
            if ($gate->isClassLevel() !== $classLevel) {
                continue;
            }

            $response = $this->resource->authorizedTo($gate->name(), $subject);
            $answers[$names[$gate->key()] ?? $gate->key()] = ['allowed' => $response->allowed(), 'message' => $response->message()];
        }

        return (object) $answers;
    }
}
