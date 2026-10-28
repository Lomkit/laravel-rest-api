<?php

namespace Lomkit\Rest\Tests\Unit\Fields;

use Closure;
use Lomkit\Rest\Fields\Boolean;
use Lomkit\Rest\Fields\Date;
use Lomkit\Rest\Fields\FieldSet;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Json;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Fields\WriteOperation;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FieldSetViewsTest extends TestCase
{
    /** @return iterable<string, array{Closure(FieldSet): FieldSet, list<string>}> */
    public static function views(): iterable
    {
        yield 'selectable' => [
            fn (FieldSet $set): FieldSet => $set->selectable(),
            ['id', 'title', 'views', 'metadata', 'published_on', 'featured'],
        ];
        yield 'sortable' => [
            fn (FieldSet $set): FieldSet => $set->sortable(),
            ['id', 'title', 'views', 'author_name', 'published_on', 'featured'],
        ];
        yield 'filterable' => [
            fn (FieldSet $set): FieldSet => $set->filterable(),
            ['id', 'title', 'views', 'author_name', 'published_on', 'featured'],
        ];
        yield 'writable on create' => [
            fn (FieldSet $set): FieldSet => $set->writableOn(WriteOperation::Create),
            ['title', 'password', 'metadata', 'slug', 'featured'],
        ];
        yield 'writable on update' => [
            fn (FieldSet $set): FieldSet => $set->writableOn(WriteOperation::Update),
            ['title', 'password', 'metadata', 'slug', 'published_on'],
        ];
    }

    /**
     * @param  Closure(FieldSet): FieldSet  $view
     * @param  list<string>  $names
     */
    #[Test]
    #[DataProvider('views')]
    public function it_returns_a_new_set_of_the_matching_fields_in_declared_order(Closure $view, array $names): void
    {
        $set = $this->postFields();

        $subset = $view($set);

        $this->assertNotSame($set, $subset);
        $this->assertSame($names, $subset->names());
        $this->assertCount(10, $set);

        foreach ($subset as $field) {
            $this->assertSame($set->get($field->name), $field);
        }
    }

    #[Test]
    public function it_chains_views(): void
    {
        $this->assertSame(
            ['title', 'metadata', 'featured'],
            $this->postFields()->selectable()->writableOn(WriteOperation::Create)->names(),
        );
    }

    #[Test]
    public function it_returns_an_empty_set_when_no_field_matches(): void
    {
        $set = FieldSet::of([Json::make('metadata'), Text::make('excerpt')->virtual()]);

        $this->assertCount(0, $set->sortable());
        $this->assertCount(0, $set->filterable());
    }

    #[Test]
    public function it_keys_the_rules_of_the_writable_fields_by_name(): void
    {
        $set = FieldSet::of([
            ID::make('id'),
            Text::make('title')->rules('max:255')->creationRules('required'),
            Text::make('password')->writeOnly()->creationRules('required'),
            Integer::make('views')->readonly()->rules('min:0'),
            Text::make('excerpt')->virtual()->creatable()->rules('max:500'),
            Date::make('published_on')->nullable()->creatable(false)->updateRules('sometimes'),
            Boolean::make('featured')->updatable(false)->creationRules('required'),
            Json::make('metadata')->nullable(),
        ]);

        $this->assertSame([
            'title' => ['string', 'max:255', 'required'],
            'password' => ['string', 'required'],
            'featured' => ['boolean', 'required'],
            'metadata' => ['nullable', 'array'],
        ], $set->rulesFor(WriteOperation::Create));

        $this->assertSame([
            'title' => ['string', 'max:255'],
            'password' => ['string'],
            'published_on' => ['nullable', 'date', 'sometimes'],
            'metadata' => ['nullable', 'array'],
        ], $set->rulesFor(WriteOperation::Update));
    }

    #[Test]
    public function it_includes_an_id_made_creatable(): void
    {
        $set = FieldSet::of([ID::make('id')->creatable()->creationRules('required'), Text::make('title')]);

        $this->assertSame(['id' => ['integer', 'required'], 'title' => ['string']], $set->rulesFor(WriteOperation::Create));
        $this->assertSame(['title' => ['string']], $set->rulesFor(WriteOperation::Update));
    }

    #[Test]
    public function it_returns_no_rules_when_no_field_is_writable(): void
    {
        $set = FieldSet::of([ID::make('id'), Integer::make('views')->readonly(), Text::make('excerpt')->virtual()]);

        $this->assertSame([], $set->rulesFor(WriteOperation::Create));
        $this->assertSame([], $set->rulesFor(WriteOperation::Update));
    }

    private function postFields(): FieldSet
    {
        return FieldSet::of([
            ID::make('id'),
            Text::make('title'),
            Text::make('password')->writeOnly()->sortable(false)->filterable(false),
            Integer::make('views')->readonly(),
            Json::make('metadata'),
            Text::make('excerpt')->virtual(),
            Text::make('author_name')->virtual()->sortUsing(fn () => null)->filterUsing(fn () => null),
            Text::make('slug')->selectable(false)->sortable(false)->filterable(false),
            Date::make('published_on')->creatable(false),
            Boolean::make('featured')->updatable(false),
        ]);
    }
}
