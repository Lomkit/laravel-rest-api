<?php

namespace Lomkit\Rest\Console\Check;

use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\RestContext;
use Lomkit\Rest\Mutations\LinkColumn;
use Lomkit\Rest\Mutations\NullableColumns;
use Lomkit\Rest\Relations\Relation;

final readonly class DetachCheck
{
    public function __construct(private Container $container) {}

    /** @param list<string> $controllers */
    public function run(array $controllers): DetachReport
    {
        $context = new RestContext(RestRequest::createFrom($this->container->make(Request::class)), $this->container);
        $columns = new NullableColumns;
        $report = new DetachReport;

        foreach ((new ReachedResources($context))->from(ControllerResources::of($controllers)) as $resource) {
            foreach ($context->resource($resource)->definition()->relations() as $relation) {
                $report = self::checkRelation($resource, $relation, $columns, $report);
            }
        }

        return $report;
    }

    /** @param class-string<Resource> $resource */
    private static function checkRelation(string $resource, Relation $relation, NullableColumns $columns, DetachReport $report): DetachReport
    {
        if (! in_array(MutationOperation::Detach, $relation->acceptedOperations(), true)) {
            return $report;
        }

        $model = $resource::newModel();
        $column = $model->isRelation($relation->relation) ? LinkColumn::detachedBy($model->{$relation->relation}())?->requiredColumn($columns) : null;

        if ($column === null) {
            return $report->withDetachable($resource, $relation->relation);
        }

        return $report->withProblem("{$resource} accepts detach on its [{$relation->relation}] relation, but {$column} does not accept null: each detach is refused with a 422. Add ->withoutOperations(MutationOperation::Detach) to the relation, or make the column nullable.");
    }
}
