<?php

namespace Lomkit\Rest\Tests\Unit\Text;

use Lomkit\Rest\Exceptions\InvalidTextSearchException;
use Lomkit\Rest\Text\FullText;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FullTextDeclarationTest extends TestCase
{
    #[Test]
    public function it_refuses_an_index_without_columns(): void
    {
        $this->expectExceptionObject(InvalidTextSearchException::emptyIndex());

        FullText::make()->index();
    }

    #[Test]
    #[DataProvider('invalidLanguages')]
    public function it_refuses_a_language_that_is_not_a_configuration_name(string $language): void
    {
        $this->expectExceptionObject(InvalidTextSearchException::invalidLanguage($language));

        FullText::make()->language($language);
    }

    /** @return array<string, array{string}> */
    public static function invalidLanguages(): array
    {
        return [
            'an empty name' => [''],
            'a quote' => ["english'"],
            'a space' => ['english simple'],
            'a qualified name' => ['pg_catalog.english'],
            'a leading digit' => ['1english'],
        ];
    }
}
