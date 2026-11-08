<?php

namespace Lomkit\Rest\Tests\Feature\Documentation;

use Dedoc\Scramble\Generator;
use Dedoc\Scramble\ScrambleServiceProvider;
use Lomkit\Rest\RestServiceProvider;
use Lomkit\Rest\Tests\Fixtures\Accounts\Accounts;
use Lomkit\Rest\Tests\Fixtures\Festival\Festival;
use Lomkit\Rest\Tests\PackageTestCase;

abstract class DocumentationTestCase extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Accounts, new Festival];
    }

    #[\Override]
    protected function getPackageProviders($app): array
    {
        return [ScrambleServiceProvider::class, RestServiceProvider::class];
    }

    /** @return array<string, mixed> */
    protected function document(): array
    {
        return app(Generator::class)();
    }

    /** @return array<string, mixed> */
    protected function operation(string $path, string $method): array
    {
        return $this->document()['paths'][$path][$method];
    }
}
