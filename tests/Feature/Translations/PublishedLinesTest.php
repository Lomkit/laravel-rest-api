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
    }

    #[Test]
    public function it_reads_the_english_line_for_a_locale_without_lines(): void
    {
        $this->assertSame('The record was changed by another request.', __('rest::errors.version_conflict', locale: 'fr'));
    }

    #[Test]
    public function it_reads_the_key_for_a_locale_without_lines_when_the_fallback_locale_is_not_english(): void
    {
        $this->app->make('translator')->setFallback('fr');

        $this->assertSame('rest::validation.utf8', __('rest::validation.utf8', locale: 'fr'));
    }
}
