<?php

namespace Lomkit\Rest\Tests\Unit\Fields;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Lomkit\Rest\Exceptions\InvalidFieldException;
use Lomkit\Rest\Fields\Enum;
use Lomkit\Rest\Fields\FieldSet;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Number;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

class FieldSetTest extends TestCase
{
    #[Test]
    public function it_rejects_a_string_entry_with_an_example_naming_the_default_owner_and_the_index(): void
    {
        $this->assertSame(
            "fields() returned a string at index 0 ('id'). Return Field objects, for example ID::make('id').",
            $this->rejectionOf(fn () => FieldSet::of(['id'])),
        );
    }

    #[Test]
    public function it_rejects_a_string_entry_with_an_example_naming_the_given_owner_and_the_index(): void
    {
        $this->assertSame(
            "PostResource::fields() returned a string at index 1 ('title'). Return Field objects, for example Text::make('title').",
            $this->rejectionOf(fn () => FieldSet::of([ID::make('id'), 'title'], 'PostResource::fields()')),
        );
    }

    /** @return iterable<string, array{mixed, string}> */
    public static function otherEntries(): iterable
    {
        yield 'int' => [42, 'int'];
        yield 'array' => [['title'], 'array'];
        yield 'null' => [null, 'null'];
        yield 'object' => [new stdClass, 'stdClass'];
    }

    #[Test]
    #[DataProvider('otherEntries')]
    public function it_rejects_any_other_entry_that_is_not_a_field(mixed $entry, string $type): void
    {
        $this->assertSame(
            "PostResource::fields() returned {$type} at index 1. Return Field objects.",
            $this->rejectionOf(fn () => FieldSet::of([ID::make('id'), $entry], 'PostResource::fields()')),
        );
    }

    #[Test]
    public function it_rejects_two_fields_with_the_same_name(): void
    {
        $this->assertSame(
            'PostResource::fields() declares the field [title] twice.',
            $this->rejectionOf(fn () => FieldSet::of([Text::make('title'), ID::make('id'), Number::make('title')], 'PostResource::fields()')),
        );
    }

    #[Test]
    public function it_keeps_the_declared_order_for_lookups_counting_and_iteration(): void
    {
        $title = Text::make('title');
        $id = ID::make('id');
        $views = Number::make('views');

        $set = FieldSet::of([$title, $id, $views]);

        $this->assertSame(['title', 'id', 'views'], $set->names());
        $this->assertCount(3, $set);
        $this->assertSame([$title, $id, $views], iterator_to_array($set));
        $this->assertTrue($set->has('id'));
        $this->assertFalse($set->has('summary'));
        $this->assertSame($views, $set->find('views'));
        $this->assertNull($set->find('summary'));
        $this->assertSame($title, $set->get('title'));
    }

    #[Test]
    public function it_matches_names_exactly(): void
    {
        $set = FieldSet::of([Text::make('title')]);

        $this->assertFalse($set->has('Title'));
        $this->assertFalse($set->has('title '));
    }

    #[Test]
    public function it_throws_when_getting_an_unknown_field(): void
    {
        $this->assertSame('Unknown field [summary].', $this->rejectionOf(fn () => FieldSet::of([ID::make('id')])->get('summary')));
    }

    #[Test]
    public function it_accepts_a_keyed_array_and_reindexes_it(): void
    {
        $title = Text::make('title');
        $views = Number::make('views');

        $set = FieldSet::of(['headline' => $title, 7 => $views]);

        $this->assertSame([0 => $title, 1 => $views], iterator_to_array($set));
        $this->assertSame(['title', 'views'], $set->names());
    }

    #[Test]
    public function it_reports_the_position_of_a_bad_entry_in_a_keyed_array(): void
    {
        $this->assertSame(
            "fields() returned a string at index 1 ('title'). Return Field objects, for example Text::make('title').",
            $this->rejectionOf(fn () => FieldSet::of(['first' => ID::make('id'), 'second' => 'title'])),
        );
    }

    #[Test]
    public function it_holds_no_field_when_built_from_an_empty_array(): void
    {
        $set = FieldSet::of([]);

        $this->assertCount(0, $set);
        $this->assertSame([], $set->names());
        $this->assertSame([], iterator_to_array($set));
    }

    #[Test]
    public function it_binds_every_field_to_the_model_and_returns_itself(): void
    {
        $model = new class extends Model
        {
            use HasUuids;

            protected function casts(): array
            {
                return ['status' => PostStatus::class];
            }
        };
        $set = FieldSet::of([ID::make('id'), Text::make('title'), Enum::make('status')]);

        $this->assertSame($set, $set->bindTo($model));
        $this->assertSame(['uuid'], $set->get('id')->baseRules());
        $this->assertSame(['string'], $set->get('title')->baseRules());
        $this->assertEquals([Rule::enum(PostStatus::class)], $set->get('status')->baseRules());
    }

    private function rejectionOf(callable $build): string
    {
        try {
            $build();
        } catch (InvalidFieldException $exception) {
            return $exception->getMessage();
        }

        $this->fail('Expected an InvalidFieldException.');
    }
}
