<?php

namespace Lomkit\Rest\Scramble\Actions;

use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Actions\ActionTarget;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Scramble\Components\KeySchema;
use Lomkit\Rest\Scramble\FieldExample;
use Lomkit\Rest\Scramble\Search\SearchExample;
use stdClass;

final class ActionExample
{
    /** @return array<string, mixed>|stdClass */
    public static function of(Resource $resource, Action $action): array|stdClass
    {
        $example = match ($action->target) {
            ActionTarget::Search => SearchExample::of($resource) ?? [],
            ActionTarget::Resources => ['resources' => [KeySchema::example($resource)]],
            ActionTarget::None => [],
        };
        $fields = [];
        $inputs = $resource
            ->definition()
            ->inputFields($action);

        foreach ($inputs as $field) {
            $fields[] = ['name' => $field->name, 'value' => $field->getExamples() === [] && $field->hasDefault() ? $field->defaultValue() : FieldExample::written($field)];
        }

        if ($fields !== []) {
            $example['fields'] = $fields;
        }

        return $example === [] ? new stdClass : $example;
    }
}
