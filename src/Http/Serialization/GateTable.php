<?php

namespace Lomkit\Rest\Http\Serialization;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Search\Plan\QueryPlan;

final readonly class GateTable
{
    private const array ABILITIES = [
        'create' => 'authorized_to_create',
        'view' => 'authorized_to_view',
        'update' => 'authorized_to_update',
        'delete' => 'authorized_to_delete',
        'restore' => 'authorized_to_restore',
        'forceDelete' => 'authorized_to_force_delete',
    ];

    /** @param list<string>|null $abilities */
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

        $abilities = array_values(array_filter($this->abilities, static fn (string $ability): bool => $ability !== 'create'));
        $row[$this->resource->context()->config()->gatesKey] = $this->answers($model, $abilities);

        return $row;
    }

    /** @return array<string, mixed> */
    public function meta(): array
    {
        if ($this->abilities === null || ! in_array('create', $this->abilities, true)) {
            return [];
        }

        return [$this->resource->context()->config()->gatesKey => $this->answers($this->resource::newModel()::class, ['create'])];
    }

    /**
     * @param  list<string>  $abilities
     * @return array<string, mixed>
     */
    private function answers(Model|string $subject, array $abilities): array
    {
        $config = $this->resource->context()->config();

        if (! $config->gateMessages) {
            trigger_deprecation('lomkit/laravel-rest-api', '2.17.0', 'In Laravel Rest Api 3 it won\'t be possible to use the old gate schema, please upgrade as quickly as possible. See: https://laravel-rest-api.lomkit.com/digging-deeper/gates#policy-message-in-gates');
        }

        $answers = [];

        foreach ($abilities as $ability) {
            if (! isset(self::ABILITIES[$ability])) {
                continue;
            }

            $response = $this->resource->authorizedTo($ability, $subject);
            $answers[$config->gateNames[self::ABILITIES[$ability]]] = $config->gateMessages
                ? ['allowed' => $response->allowed(), 'message' => $response->message()]
                : $response->allowed();
        }

        return $answers;
    }
}
