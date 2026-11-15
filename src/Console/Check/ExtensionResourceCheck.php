<?php

namespace Lomkit\Rest\Console\Check;

use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Lomkit\Rest\Extensions\Contexts\ResourceCheckContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\ChecksResources;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\RestContext;
use Throwable;

final readonly class ExtensionResourceCheck
{
    public function __construct(private Container $container) {}

    /** @param list<string> $controllers */
    public function run(array $controllers): ExtensionResourceReport
    {
        $context = new RestContext(RestRequest::createFrom($this->container->make(Request::class)), $this->container);
        $report = new ExtensionResourceReport;

        if (! BuiltExtensions::in($context) || ! $context->extensions()->hasKind(ChecksResources::class)) {
            return $report;
        }

        foreach ((new ReachedResources($context))->from(ControllerResources::of($controllers)) as $resource) {
            $extensions = $context->extensions()->applicable($resource, ChecksResources::class);
            ksort($extensions);

            foreach ($extensions as $name => $extension) {
                $report = self::check($report, $name, $extension, $resource);
            }
        }

        return $report;
    }

    /** @param class-string<Resource> $resource */
    private static function check(ExtensionResourceReport $report, string $name, Extension&ChecksResources $extension, string $resource): ExtensionResourceReport
    {
        $class = $extension::class;
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
