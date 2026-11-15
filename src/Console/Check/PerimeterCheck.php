<?php

namespace Lomkit\Rest\Console\Check;

use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Actions\ActionTarget;
use Lomkit\Rest\Attributes\WithoutPerimeter;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\RestContext;
use Lomkit\Rest\Query\ExtensionRestrictions;
use ReflectionClass;
use ReflectionMethod;

final readonly class PerimeterCheck
{
    /** @param list<string> $controllers */
    public function run(array $controllers, DeclarationReport $declarations, RestContext $context): PerimeterReport
    {
        $resources = ControllerResources::of($controllers);
        $report = new PerimeterReport;
        $engines = new EngineReach($context);

        foreach ((new ReachedResources($context))->from($resources) as $resource) {
            $restrictions = $engines->extensionsBuilt() ? (ExtensionRestrictions::of($context->resource($resource))?->names() ?? []) : [];
            $report = self::checkRows($resource, $restrictions, $report);
            $problem = $engines->problem($context->resource($resource));
            $report = $problem === null ? $report : $report->withRowProblem($problem);
        }

        foreach ($resources as $resource) {
            foreach ($declarations->actionsOf($resource) as $action) {
                $report = self::checkAction($resource, $action, $report);
            }
        }

        return $report;
    }

    /**
     * @param  class-string<Resource>  $resource
     * @param  list<string>  $restrictions
     */
    private static function checkRows(string $resource, array $restrictions, PerimeterReport $report): PerimeterReport
    {
        $report = $restrictions === [] ? $report : $report->withRestriction($resource, $restrictions);

        if ((new ReflectionMethod($resource, 'perimeterQuery'))->getDeclaringClass()->getName() !== Resource::class) {
            return $report->withPerimeter($resource);
        }

        if (self::marksWithoutPerimeter($resource)) {
            return $report->withoutPerimeter($resource);
        }

        return $report->withRowProblem($restrictions === [] ? self::unrestricted($resource) : self::restricted($resource, $restrictions));
    }

    private static function unrestricted(string $resource): string
    {
        return "{$resource} does not override perimeterQuery(): every row is visible to users who pass viewAny. Override perimeterQuery() to limit the rows, or mark the resource with #[WithoutPerimeter] when every row is meant for these users.";
    }

    /** @param list<string> $restrictions */
    private static function restricted(string $resource, array $restrictions): string
    {
        $names = implode(', ', $restrictions);

        return "{$resource} does not override perimeterQuery(): only the extensions [{$names}] restrict its rows. Override perimeterQuery() to limit the rows, or mark the resource with #[WithoutPerimeter] when the rows that these extensions leave are meant for every user who passes viewAny.";
    }

    /** @param class-string<Resource> $resource */
    private static function marksWithoutPerimeter(string $resource): bool
    {
        for ($class = new ReflectionClass($resource); $class !== false; $class = $class->getParentClass()) {
            if ($class->getAttributes(WithoutPerimeter::class) !== []) {
                return true;
            }
        }

        return false;
    }

    /** @param class-string<Resource> $resource */
    private static function checkAction(string $resource, Action $action, PerimeterReport $report): PerimeterReport
    {
        if ($action->target === ActionTarget::None) {
            return $report;
        }

        $ability = $action->authorizeEach();

        if ($ability !== null) {
            return $report->withCheckedAction($resource, $action::class, $ability);
        }

        return $report->withActionProblem($action::class." of {$resource} runs on rows without authorizeEach(): no policy ability is checked on each row. Return the ability to check from authorizeEach(), such as 'update', or 'view' for an action that only reads.");
    }
}
