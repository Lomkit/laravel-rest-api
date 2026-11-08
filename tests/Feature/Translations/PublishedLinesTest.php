<?php

namespace Lomkit\Rest\Tests\Feature\Translations;

use Illuminate\Support\ServiceProvider;
use Lomkit\Rest\RestServiceProvider;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class PublishedLinesTest extends PackageTestCase
{
    #[Test]
    public function it_publishes_the_lines_under_the_rest_lang_tag(): void
    {
        $paths = ServiceProvider::pathsToPublish(RestServiceProvider::class, 'rest-lang');

        $this->assertSame([realpath(__DIR__.'/../../../lang')], array_map(realpath(...), array_keys($paths)));
        $this->assertSame([$this->app->langPath('vendor/rest')], array_values($paths));
    }

    #[Test]
    public function it_reads_the_lines_under_the_rest_namespace(): void
    {
        $this->assertSame('The record was changed by another request.', __('rest::errors.version_conflict'));
        $this->assertSame('L\'élément a été modifié par une autre requête.', __('rest::errors.version_conflict', locale: 'fr'));
    }
}
