<?php

namespace Lomkit\Rest\Tests\Fixtures\Extensions;

use Illuminate\Contracts\Config\Repository;
use Lomkit\Rest\Extensions\Extension;

final class ConfiguredExtension extends Extension implements NotesResources
{
    public const string RESOURCES = 'extension_fixtures.applies_to';

    public function __construct(private readonly Repository $config)
    {
        ExtensionLog::$built[] = self::class;
    }

    #[\Override]
    public function appliesTo(string $resource): bool
    {
        ExtensionLog::$asked[] = self::class.' '.$resource;
        $resources = $this->config->get(self::RESOURCES, []);

        return is_array($resources) && in_array($resource, $resources, true);
    }
}
