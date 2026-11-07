<?php

namespace Lomkit\Rest\Tests\Feature\Events\Stubs;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Mutations\MutationNode;

final class HookLog
{
    public static function entry(Resource $resource, string $hook, MutationNode $entry, Model $model): void
    {
        EventLog::$payloads[] = $entry;
        EventLog::record(implode(' ', [
            $hook,
            $entry->operation->value,
            json_encode($entry->key),
            json_encode($entry->attributes),
            implode(',', array_keys($entry->relations ?? [])) ?: '-',
            $model->exists ? $model->getKey() : 'new',
            class_basename($resource->request()),
        ]));
    }

    public static function row(Resource $resource, string $hook, Model $model): void
    {
        EventLog::record(implode(' ', [
            $hook,
            $model->getKey(),
            class_basename($resource->request()),
        ]));
    }
}
