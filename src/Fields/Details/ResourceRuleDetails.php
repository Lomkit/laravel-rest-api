<?php

namespace Lomkit\Rest\Fields\Details;

use JsonSerializable;
use Lomkit\Rest\Fields\WriteRules;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use stdClass;

final readonly class ResourceRuleDetails implements JsonSerializable
{
    /**
     * @param  array<array-key, mixed>  $all
     * @param  array<array-key, mixed>  $create
     * @param  array<array-key, mixed>  $update
     */
    public function __construct(
        private array $all,
        private array $create,
        private array $update,
    ) {}

    public static function of(Resource $resource, RestRequest $request): self
    {
        return new self($resource->rules($request), $resource->createRules($request), $resource->updateRules($request));
    }

    /** @return array{all: stdClass, create: stdClass, update: stdClass} */
    #[\Override]
    public function jsonSerialize(): array
    {
        return [
            'all' => self::describe($this->all),
            'create' => self::describe($this->create),
            'update' => self::describe($this->update),
        ];
    }

    /** @param array<array-key, mixed> $rules */
    private static function describe(array $rules): stdClass
    {
        return (object) array_map(fn (mixed $list): array => (new RuleDescriptions(WriteRules::listOf($list)))->toArray(), $rules);
    }
}
