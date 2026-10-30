<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class IncludeAliasValidationTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing];
    }

    /**
     * @param  list<array<string, string>>  $includes
     * @param  array<string, list<string>>  $errors
     */
    #[Test]
    #[DataProvider('invalidAliases')]
    public function it_rejects_an_alias_that_cannot_name_the_include(array $includes, array $errors): void
    {
        $response = $this->postJson('/api/authors/search', [
            'search' => ['includes' => $includes],
        ]);

        $response->assertUnprocessable()->assertJsonPath('errors', $errors);
    }

    /** @return array<string, array{list<array<string, string>>, array<string, list<string>>}> */
    public static function invalidAliases(): array
    {
        return [
            'an alias on a nested relation' => [[['relation' => 'posts.author', 'alias' => 'writers']], [
                'search.includes.0.alias' => ['The search.includes.0.alias is not allowed on a nested relation, use a nested include instead.'],
            ]],
            'the gates key' => [[['relation' => 'posts', 'alias' => 'gates']], [
                'search.includes.0.alias' => ['The search.includes.0.alias is reserved.'],
            ]],
            'the name of a field' => [[['relation' => 'posts', 'alias' => 'last_name']], [
                'search.includes.0.alias' => ['The search.includes.0.alias conflicts with a field of this resource.'],
            ]],
            'the name of a write only field' => [[['relation' => 'posts', 'alias' => 'api_token']], [
                'search.includes.0.alias' => ['The search.includes.0.alias conflicts with a field of this resource.'],
            ]],
            'the name of a virtual field' => [[['relation' => 'posts', 'alias' => 'initials']], [
                'search.includes.0.alias' => ['The search.includes.0.alias conflicts with a field of this resource.'],
            ]],
            'the name of a relation' => [[['relation' => 'posts'], ['relation' => 'posts', 'alias' => 'posts']], [
                'search.includes.1.alias' => ['The search.includes.1.alias conflicts with a relation of this resource.'],
            ]],
            'an alias that is not an identifier' => [[['relation' => 'posts', 'alias' => '0']], [
                'search.includes.0.alias' => ['The search.includes.0.alias field format is invalid.'],
            ]],
            'the same alias twice' => [[['relation' => 'posts', 'alias' => 'articles'], ['relation' => 'posts', 'alias' => 'articles']], [
                'search.includes.0.alias' => ['The search.includes.0.alias is already used by another include.'],
                'search.includes.1.alias' => ['The search.includes.1.alias is already used by another include.'],
            ]],
        ];
    }
}
