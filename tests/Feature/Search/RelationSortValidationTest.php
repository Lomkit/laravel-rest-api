<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Search\Stubs\LeadingAuthorController;
use Lomkit\Rest\Tests\Fixtures\Library\Library;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class RelationSortValidationTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing, new Library];
    }

    #[Test]
    #[DataProvider('unreachableFields')]
    public function it_refuses_a_field_that_no_to_one_relation_sorts(string $uri, string $field): void
    {
        Rest::resource('leading-authors', LeadingAuthorController::class)->register();

        $response = $this->postJson($uri, ['search' => ['sorts' => [['field' => $field]]]]);

        $response->assertUnprocessable()->assertExactJson([
            'message' => 'The selected search.sorts.0.field is invalid.',
            'errors' => ['search.sorts.0.field' => ['The selected search.sorts.0.field is invalid.']],
        ]);
    }

    /** @return array<string, array{string, string}> */
    public static function unreachableFields(): array
    {
        return [
            'a has many relation' => ['/api/authors/search', 'posts.title'],
            'a belongs to many relation' => ['/api/authors/search', 'tags.name'],
            'a morph many relation' => ['/api/books/search', 'labels.text'],
            'a morph to relation' => ['/api/labels/search', 'labelable.title'],
            'a boolean field' => ['/leading-authors/search', 'leadPost.featured'],
            'a field declared sortable(false)' => ['/api/posts/search', 'author.id'],
            'a json field' => ['/api/posts/search', 'author.settings'],
            'a virtual field with a custom sort' => ['/api/posts/search', 'author.full_name'],
            'a write only field' => ['/api/posts/search', 'author.api_token'],
            'an undeclared field' => ['/api/posts/search', 'author.nickname'],
            'an undeclared relation' => ['/api/posts/search', 'editor.name'],
            'two relations' => ['/api/posts/search', 'author.posts.title'],
        ];
    }
}
