<?php

namespace Lomkit\Rest\Scramble\Actions;

use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Actions\ActionTarget;
use Lomkit\Rest\Fields\Details\RuleDescriptions;
use Lomkit\Rest\Fields\FieldSet;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Scramble\ComponentRegistry;
use Lomkit\Rest\Scramble\Components\KeySchema;
use Lomkit\Rest\Scramble\FieldSchema;
use Lomkit\Rest\Scramble\Schemas;
use Lomkit\Rest\Scramble\Search\SearchSchema;
use Lomkit\Rest\Scramble\Types\Nothing;
use Lomkit\Rest\Scramble\Types\OneOf;
use Lomkit\Rest\Validation\Search\SearchShape;

final readonly class ActionSchema
{
    public function __construct(
        private ComponentRegistry $components,
    ) {}

    public function body(Resource $resource, Action $action): ObjectType
    {
        $fields = $resource
            ->definition()
            ->inputFields($action);
        $target = $action->target;

        $properties = [
            'search' => $target === ActionTarget::Search ? (new SearchSchema($this->components))->reference($resource, SearchShape::Rows) : new Nothing,
            'resources' => $target === ActionTarget::Resources ? KeySchema::capped($resource, $action->maxResources) : new Nothing,
            'fields' => count($fields) === 0 ? new Nothing : self::fields($fields, $resource->context()->limits()->sizes->fields),
        ];

        if ($action->requiresConfirmation()) {
            $properties['confirmation'] = (new StringType)->setDescription('The token of the 428 answer of this action, sent back with the same body to run the action');
        }

        return Schemas::required($properties, self::optional($target), self::description($action));
    }

    /** @return list<string> */
    private static function optional(ActionTarget $target): array
    {
        return match ($target) {
            ActionTarget::Search => ['resources', 'fields', 'confirmation'],
            ActionTarget::Resources => ['search', 'fields', 'confirmation'],
            ActionTarget::None => ['search', 'resources', 'fields', 'confirmation'],
        };
    }

    private static function fields(FieldSet $fields, int $max): ArrayType
    {
        $entries = [];

        foreach ($fields as $field) {
            $entries[] = Schemas::closed(['name' => (new StringType)->const($field->name), 'value' => self::value($field->getRules()->shared(), FieldSchema::written($field))], ['name']);
        }

        $list = Schemas::list(count($entries) === 1 ? $entries[0] : new OneOf($entries), 'The values of the fields of the action: one `{"name", "value"}` entry for each field that the request gives');
        $list->setMax($max);

        return $list;
    }

    /** @param list<mixed> $rules */
    private static function value(array $rules, Type $type): Type
    {
        $described = (new RuleDescriptions($rules))->toArray();

        if ($described === []) {
            return $type;
        }

        return $type->setDescription('Rules: '.implode(', ', $described));
    }

    private static function description(Action $action): string
    {
        return match ($action->target) {
            ActionTarget::Search => 'The action runs on the rows that `search` matches, every row with an empty `search`'.($action->maxResources === null ? '' : ", at most {$action->maxResources}: a search that matches more answers 422"),
            ActionTarget::Resources => 'The action runs on the rows of the keys in `resources`',
            ActionTarget::None => 'The action runs without rows',
        };
    }
}
