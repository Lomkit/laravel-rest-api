<?php

namespace Lomkit\Rest\Console\Check;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Lomkit\Rest\Config\RestConfig;
use Lomkit\Rest\Exceptions\InvalidConfigException;
use Lomkit\Rest\Exceptions\InvalidExtensionException;
use Lomkit\Rest\Extensions\ExtensionEntry;
use Lomkit\Rest\Extensions\ExtensionFactory;
use Lomkit\Rest\Extensions\ExtensionList;
use Lomkit\Rest\Extensions\RuntimeExtensions;
use Throwable;

/** @internal */
final readonly class ExtensionCheck
{
    public function __construct(private Container $container) {}

    public function run(): ExtensionReport
    {
        $report = new ExtensionReport;

        try {
            $configured = RestConfig::from($this->container->make(Repository::class))->extensions;
        } catch (InvalidConfigException $exception) {
            $configured = [];
            $report = $report->withProblem($exception->getMessage());
        }

        $registered = $this->container->make(RuntimeExtensions::class)->all();
        $list = ExtensionList::merge($configured, $registered);
        $factory = new ExtensionFactory($this->container);

        foreach ($list->refused as $exception) {
            $report = $report->withProblem($exception->getMessage());
        }

        foreach ($list->entries as $entry) {
            $report = $report->withEntry($entry);

            if ($entry->disablesNothing()) {
                $report = $report->withNote(self::idle($entry->name));
            }

            if ($entry->disabled || $entry->class === null) {
                continue;
            }

            try {
                $factory->make($entry->name, $entry->class);
            } catch (Throwable $exception) {
                $report = $report->withProblem(self::problem($entry, $exception));
            }
        }

        return $report;
    }

    private static function idle(string $name): string
    {
        return "The [{$name}] entry of the [rest.extensions] config is false, but no extension is registered under this name, so it disables nothing. Remove it, or use the name that the package registers.";
    }

    private static function problem(ExtensionEntry $entry, Throwable $exception): string
    {
        if ($exception instanceof InvalidExtensionException && $exception->namesExtension($entry->name)) {
            return $exception->getMessage();
        }

        return "The [{$entry->name}] extension {$entry->class} cannot be built: {$exception->getMessage()}";
    }
}
