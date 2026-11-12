<?php

namespace Lomkit\Rest\Console\Check;

use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Actions\ActionTarget;
use Lomkit\Rest\Attributes\WithoutPerimeter;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\RestContext;
use ReflectionClass;
use ReflectionMethod;

final readonly class PerimeterCheck
{
    public function __construct(private Container $container) {}

    /** @param list<string> $controllers */
    public function run(array $controllers, DeclarationReport $declarations): PerimeterReport
    {
        $context = new RestContext(RestRequest::createFrom($this->container->make(Request::class)), $this->container);
        $resources = ControllerResources::of($controllers);
        $report = new PerimeterReport;

        foreach ((new ReachedResources($context))->from($resources) as $resource) {
            $report = self::checkRows($resource, $report);
        }

        foreach ($resources as $resource) {
            foreach ($declarations->actionsOf($resource) as $action) {
                $report = self::checkAction($resource, $action, $report);
            }
        }

        return $report;
    }

    /** @param class-string<Resource> $resource */
    private static function checkRows(string $resource, PerimeterReport $report): PerimeterReport
    {
        if ((new ReflectionMethod($resource, 'perimeterQuery'))->getDeclaringClass()->getName() !== Resource::class) {
            return $report->withPerimeter($resource);
        }

        if (self::marksWithoutPerimeter($resource)) {
            return $report->withoutPerimeter($resource);
        }

        return $report->withRowProblem("{$resource} does not override perimeterQuery(): every row is visible to users who pass viewAny. Override perimeterQuery() to limit the rows, or mark the resource with #[WithoutPerimeter] when every row is meant for these users.");
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
