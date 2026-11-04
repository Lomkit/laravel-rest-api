<?php

namespace Lomkit\Rest\Validation;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;

final readonly class KeysGrammar
{
    private function __construct(
        private ID $id,
        private KeyLookup $lookup,
        private int $max,
    ) {}

    /** @param Closure(): Builder<Model> $query */
    public static function of(Resource $resource, RestRequest $request, Closure $query, int $max, KeyRead $read = KeyRead::Keys): self
    {
        $id = self::idOf($resource, $request);

        return new self($id, new KeyLookup($query, $id, $read), $max);
    }

    public function parse(Cursor $resources): KeyList
    {
        $items = $resources->items($this->max);

        if ($items === null) {
            return new KeyList([], [], $this->id);
        }

        $rules = [];
        $groups = [];

        foreach ($items as $item) {
            $rules[$item->path] = $this->id->baseRules();
            $key = $this->id->parseKey($item->value);

            if ($key !== null) {
                $groups[$this->id->normalizeKey($key)][] = [$item, $key];
            }
        }

        foreach ($groups as $group) {
            if (count($group) === 1) {
                $rules[$group[0][0]->path][] = $this->lookup->expect($group[0][1]);

                continue;
            }

            foreach ($group as [$copy]) {
                $copy->reject('validation.distinct');
            }
        }

        return new KeyList(array_values(array_map(fn (array $group): int|string => $group[0][1], $groups)), $rules, $this->id, $this->lookup);
    }

    public static function idOf(Resource $resource, RestRequest $request): ID
    {
        $model = $resource::newModel();
        $field = $resource->getFields($request)->find($model->getKeyName());

        if ($field instanceof ID) {
            return $field;
        }

        return ID::make($model->getKeyName())->keyType(in_array($model->getKeyType(), ['int', 'integer'], true) ? 'int' : 'string');
    }
}
