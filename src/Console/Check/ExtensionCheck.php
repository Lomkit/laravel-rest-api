<?php

namespace Lomkit\Rest\Console\Check;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Container\Container;
use LogicException;
use Lomkit\Rest\Config\RestConfig;
use Lomkit\Rest\Exceptions\InvalidExtensionException;
use Lomkit\Rest\Extensions\ExtensionEntry;
use Lomkit\Rest\Extensions\ExtensionFactory;
use Lomkit\Rest\Extensions\ExtensionList;
use Lomkit\Rest\Extensions\RuntimeExtensions;

final readonly class ExtensionCheck
{
    public function __construct(private Container $container) {}

    public function run(): ExtensionReport
    {
        $configured = RestConfig::from($this->container->make(Repository::class))->extensions;
        $registered = $this->container->make(RuntimeExtensions::class)->all();

        try {
            $list = ExtensionList::merge($configured, $registered);
        } catch (InvalidExtensionException $exception) {
            return (new ExtensionReport)->withProblem($exception->getMessage());
        }

        $factory = new ExtensionFactory($this->container);
        $report = new ExtensionReport;

        foreach ($list->entries as $entry) {
            $report = $report->withEntry($entry);

            if ($entry->disabled || $entry->class === null) {
                continue;
            }

            try {
                $factory->make($entry->name, $entry->class);
            } catch (LogicException|BindingResolutionException $exception) {
                $report = $report->withProblem(self::problem($entry, $exception));
            }
        }

        return $report;
    }

    private static function problem(ExtensionEntry $entry, LogicException|BindingResolutionException $exception): string
    {
        if ($exception instanceof InvalidExtensionException && $exception->namesExtension($entry->name)) {
            return $exception->getMessage();
        }

        return "The [{$entry->name}] extension {$entry->class} cannot be built: {$exception->getMessage()}";
    }
}
