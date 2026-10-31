<?php

namespace Lomkit\Rest\Tests\Feature\Text;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class TextValidationTest extends TextSearchTestCase
{
    /**
     * @param  array<string, mixed>  $search
     * @param  array<string, list<string>>  $errors
     */
    #[Test]
    #[DataProvider('invalidSearches')]
    public function it_rejects_a_text_search_the_resource_cannot_run(string $resource, array $search, array $errors): void
    {
        $response = $this->search($resource, $search);

        $response->assertUnprocessable()->assertJsonPath('errors', $errors);
    }

    /** @return array<string, array{string, array<string, mixed>, array<string, list<string>>}> */
    public static function invalidSearches(): array
    {
        $mode = ['search.text.mode' => ['The selected search.text.mode is invalid.']];
        $relevance = ['search.sorts.0.field' => ['The selected search.sorts.0.field is invalid.']];

        return [
            'a prefix search on contains' => ['contains-posts', ['text' => ['value' => 'lam', 'mode' => 'prefix']], $mode],
            'a websearch on contains' => ['contains-posts', ['text' => ['value' => 'lamp -desk', 'mode' => 'websearch']], $mode],
            'an unknown mode' => ['contains-posts', ['text' => ['value' => 'lamp', 'mode' => 'fuzzy']], $mode],
            'a mode that is not a string' => ['contains-posts', ['text' => ['value' => 'lamp', 'mode' => 1]], [
                'search.text.mode' => ['The search.text.mode field must be a string.', 'The selected search.text.mode is invalid.'],
            ]],
            'a relevance sort under contains' => ['contains-posts', ['text' => ['value' => 'lamp'], 'sorts' => [['field' => '@relevance']]], $relevance],
            'a relevance sort without text' => ['full-text-posts', ['sorts' => [['field' => '@relevance']]], $relevance],
            'a relevance sort with a blank text' => ['full-text-posts', ['text' => ['value' => ' '], 'sorts' => [['field' => '@relevance']]], $relevance],
            'a relevance sort in an include' => ['contains-posts', ['text' => ['value' => 'lamp'], 'includes' => [['relation' => 'author', 'sorts' => [['field' => '@relevance']]]]], [
                'search.includes.0.sorts.0.field' => ['The selected search.includes.0.sorts.0.field is invalid.'],
            ]],
            'a relevance sort in an include of a resource that ranks' => ['full-text-posts-authors', ['text' => ['value' => 'ada'], 'includes' => [['relation' => 'posts', 'sorts' => [['field' => '@relevance']]]]], [
                'search.includes.0.sorts.0.field' => ['The selected search.includes.0.sorts.0.field is invalid.'],
            ]],
            'a text in an include' => ['contains-posts', ['includes' => [['relation' => 'author', 'text' => ['value' => 'ada']]]], [
                'search.includes.0.text' => ['The search.includes.0.text field is prohibited.'],
            ]],
            'a trashed value in the text' => ['contains-authors', ['text' => ['value' => 'ada', 'trashed' => 'with']], [
                'search.text.trashed' => ['The search.text.trashed field is prohibited.'],
            ]],
            'a text longer than 255 characters' => ['contains-posts', ['text' => ['value' => str_repeat('a', 256)]], [
                'search.text.value' => ['The search.text.value field must not be greater than 255 characters.'],
            ]],
            'a text on a resource without text search' => ['api/posts', ['text' => ['value' => 'lamp']], [
                'search.text' => ['The search.text field is prohibited.'],
            ]],
            'a text that is not an object' => ['contains-posts', ['text' => 'lamp'], [
                'search.text' => ['The search.text field must be an array.'],
            ]],
        ];
    }

    /**
     * @param  array<string, mixed>  $search
     * @param  array<string, list<string>>  $errors
     */
    #[Test]
    #[DataProvider('invalidTrashedValues')]
    public function it_rejects_a_trashed_value_the_resource_cannot_apply(string $resource, array $search, array $errors): void
    {
        $response = $this->search($resource, $search);

        $response->assertUnprocessable()->assertJsonPath('errors', $errors);
    }

    /** @return array<string, array{string, array<string, mixed>, array<string, list<string>>}> */
    public static function invalidTrashedValues(): array
    {
        return [
            'a model without soft deletes' => ['contains-posts', ['trashed' => 'with'], [
                'search.trashed' => ['The search.trashed field is prohibited.'],
            ]],
            'a model without soft deletes and a text' => ['contains-posts', ['text' => ['value' => 'lamp'], 'trashed' => 'only'], [
                'search.trashed' => ['The search.trashed field is prohibited.'],
            ]],
            'an unknown value' => ['contains-authors', ['trashed' => 'all'], [
                'search.trashed' => ['The selected search.trashed is invalid.'],
            ]],
            'a null value' => ['contains-authors', ['trashed' => null], [
                'search.trashed' => ['The selected search.trashed is invalid.'],
            ]],
            'a trashed value in an include' => ['contains-posts', ['includes' => [['relation' => 'author', 'trashed' => 'with']]], [
                'search.includes.0.trashed' => ['The search.includes.0.trashed field is prohibited.'],
            ]],
        ];
    }
}
