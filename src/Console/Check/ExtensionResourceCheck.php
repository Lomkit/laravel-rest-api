<?php

namespace Lomkit\Rest\Console\Check;

use Lomkit\Rest\Exceptions\InvalidMetaExtensionException;
use Lomkit\Rest\Extensions\Contexts\ResourceCheckContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\ExtensionRegistry;
use Lomkit\Rest\Extensions\Kinds\AddsSearchMeta;
use Lomkit\Rest\Extensions\Kinds\ChecksResources;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\RestContext;
use Lomkit\Rest\Search\Run\ExtensionSearchMeta;
use Throwable;

final readonly class ExtensionResourceCheck
{
    /** @param list<string> $controllers */
    public function run(array $controllers, RestContext $context): ExtensionResourceReport
    {
        $report = new ExtensionResourceReport;

        if (! BuiltExtensions::in($context) || $context->extensions()->all() === []) {
            return $report;
        }

        $extensions = $context->extensions()->ofKind(ChecksResources::class);

        foreach ((new ReachedResources($context))->from(ControllerResources::of($controllers)) as $resource) {
            $report = self::checkMetaName($report, $context, $resource);

            foreach ($extensions as $name => $extension) {
                $report = self::check($report, $context->extensions(), $name, $extension, $resource);
            }
        }

        return $report;
    }

    /** @param class-string<Resource> $resource */
    private static function checkMetaName(ExtensionResourceReport $report, RestContext $context, string $resource): ExtensionResourceReport
    {
        $problem = UndecidedExtension::first($context->extensions(), AddsSearchMeta::class, $resource);

        if ($problem === null) {
            try {
                ExtensionSearchMeta::extensions($context->resource($resource));
            } catch (InvalidMetaExtensionException $exception) {
                $problem = $exception->getMessage();
            }
        }

        return $problem === null ? $report : $report->withProblem($problem);
    }

    /** @param class-string<Resource> $resource */
    private static function check(ExtensionResourceReport $report, ExtensionRegistry $registry, string $name, Extension&ChecksResources $extension, string $resource): ExtensionResourceReport
    {
        $class = $extension::class;
        $undecided = UndecidedExtension::problem($registry, $name, $extension, $resource);

        if ($undecided !== null) {
            return $report->withProblem($undecided);
        }

        if (! $registry->appliesTo($name, $resource)) {
            return $report;
        }

        $problems = [];
        $reported = function (string $message) use (&$problems, $name, $class, $resource): void {
            $problems[] = "The [{$name}] extension {$class} reports {$resource}: {$message}";
        };

        try {
            $extension->checkResource(new ResourceCheckContext($resource, $reported));
        } catch (Throwable $exception) {
            $problems[] = "The [{$name}] extension {$class} throws while it checks {$resource}: {$exception->getMessage()}";
        }

        $report = $report->withCheck($name, $resource);

        foreach ($problems as $problem) {
            $report = $report->withProblem($problem);
        }

        return $report;
    }
}
