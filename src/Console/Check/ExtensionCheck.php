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
        $config = $this->container->make(Repository::class);
        [$configured, $report] = self::listed($config, RestConfig::EXTENSIONS, $report);
        [$disabled, $report] = self::listed($config, RestConfig::DISABLED_EXTENSIONS, $report);

        $list = ExtensionList::of($configured, $disabled, $this->container->make(RuntimeExtensions::class)->all());
        $named = $list->named();
        $factory = new ExtensionFactory($this->container);

        foreach ($named->refused as $exception) {
            $report = $report->withProblem($exception->getMessage());
        }

        foreach ($list->idle as $class) {
            $report = $report->withNote(self::idle($class));
        }

        foreach ($named->entries as $entry) {
            $report = $report->withEntry($entry);

            if ($entry->disabled || in_array($entry->name, $named->shared, true)) {
                continue;
            }

            try {
                $factory->make($entry->class);
            } catch (Throwable $exception) {
                $report = $report->withProblem(self::problem($entry, $exception));
            }
        }

        return $report;
    }

    /** @return array{list<mixed>, ExtensionReport} */
    private static function listed(Repository $config, string $key, ExtensionReport $report): array
    {
        try {
            return [RestConfig::extensionClasses($config, $key), $report];
        } catch (InvalidConfigException $exception) {
            return [[], $report->withProblem($exception->getMessage())];
        }
    }

    private static function idle(string $class): string
    {
        return "The [rest.disabled_extensions] config lists {$class}, but no extension of this class is registered or configured, so it disables nothing. Remove it, or list the exact class that the package registers.";
    }

    private static function problem(ExtensionEntry $entry, Throwable $exception): string
    {
        if ($exception instanceof InvalidExtensionException && $exception->concerns($entry->class)) {
            return $exception->getMessage();
        }

        return "The [{$entry->name}] extension {$entry->class} cannot be built: {$exception->getMessage()}";
    }
}
