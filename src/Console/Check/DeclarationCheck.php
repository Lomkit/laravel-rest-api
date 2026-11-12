<?php

namespace Lomkit\Rest\Console\Check;

use Closure;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use LogicException;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Http\DeclarationList;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\RestContext;
use Lomkit\Rest\Metrics\Metric;

final readonly class DeclarationCheck
{
    public function __construct(private Container $container) {}

    /** @param list<string> $controllers */
    public function run(array $controllers): DeclarationReport
    {
        $context = new RestContext(RestRequest::createFrom($this->container->make(Request::class)), $this->container);
        $report = new DeclarationReport;

        foreach (ControllerResources::of($controllers) as $resource) {
            $declarations = $context->resource($resource)->definition()->declarations();
            $report = self::build($resource, $declarations->actions(), $report, fn (DeclarationReport $report, Action $action): DeclarationReport => $report->withAction($resource, $action));
            $report = self::build($resource, $declarations->metrics(), $report, fn (DeclarationReport $report): DeclarationReport => $report->withMetric());
        }

        return $report;
    }

    /**
     * @template T of Action|Metric
     *
     * @param  DeclarationList<T>  $declarations
     * @param  Closure(DeclarationReport, T): DeclarationReport  $built
     */
    private static function build(string $resource, DeclarationList $declarations, DeclarationReport $report, Closure $built): DeclarationReport
    {
        foreach ($declarations->indexes() as $index) {
            try {
                $report = $built($report, $declarations->at($index));
            } catch (LogicException|BindingResolutionException $exception) {
                $report = $report->withProblem("{$resource}: {$exception->getMessage()}");
            }
        }

        return $report;
    }
}
