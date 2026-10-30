<?php

namespace Lomkit\Rest\Tests\Unit\Query;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Fields\FieldSet;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Query\AttributeWriter;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AttributeWriterTest extends TestCase
{
    #[Test]
    public function it_fills_the_fields_then_runs_the_fill_callbacks_then_applies_the_owned_keys(): void
    {
        $post = new Post;
        $fields = FieldSet::of([
            Text::make('headline')->virtual()->fillUsing(function (Model $post, mixed $value): void {
                $post->forceFill(['title' => $value, 'summary' => $post->getAttribute('title')]);
            }),
            Text::make('title'),
            Integer::make('author_id'),
        ]);

        $written = (new AttributeWriter($fields))->write($post, MutationOperation::Create, ['headline' => 'Annual report', 'title' => 'Draft', 'author_id' => 5], ['author_id' => 9]);

        $this->assertSame($post, $written);
        $this->assertSame(['title' => 'Annual report', 'author_id' => 9, 'summary' => 'Draft'], $post->getAttributes());
    }

    /** @param array<string, mixed> $expected */
    #[Test]
    #[DataProvider('writes')]
    public function it_fills_only_the_fields_writable_on_the_operation(MutationOperation $operation, array $expected): void
    {
        $post = new Post;
        $fields = FieldSet::of([ID::make('id'), Text::make('title'), Text::make('summary')->nullable(), Text::make('slug')->updatable(false), Text::make('initials')->virtual()]);

        (new AttributeWriter($fields))->write($post, $operation, ['id' => 5, 'title' => 'Annual report', 'summary' => null, 'slug' => 'annual-report', 'initials' => 'AR', 'views' => 3]);

        $this->assertSame($expected, $post->getAttributes());
    }

    /** @return array<string, array{MutationOperation, array<string, mixed>}> */
    public static function writes(): array
    {
        return [
            'create' => [MutationOperation::Create, ['title' => 'Annual report', 'summary' => null, 'slug' => 'annual-report']],
            'update' => [MutationOperation::Update, ['title' => 'Annual report', 'summary' => null]],
            'sync' => [MutationOperation::Sync, ['title' => 'Annual report', 'summary' => null]],
            'toggle' => [MutationOperation::Toggle, ['title' => 'Annual report', 'summary' => null]],
            'attach' => [MutationOperation::Attach, []],
            'detach' => [MutationOperation::Detach, []],
        ];
    }

    #[Test]
    public function it_applies_the_owned_keys_on_attach_and_detach(): void
    {
        $post = new Post;

        (new AttributeWriter(FieldSet::of([Text::make('title')])))->write($post, MutationOperation::Detach, ['title' => 'Annual report'], ['author_id' => null]);

        $this->assertSame(['author_id' => null], $post->getAttributes());
    }

    #[Test]
    public function it_leaves_the_model_untouched_without_attributes(): void
    {
        $post = new Post(['title' => 'Annual report']);

        (new AttributeWriter(FieldSet::of([Text::make('title'), Text::make('summary')])))->write($post, MutationOperation::Update, []);

        $this->assertSame(['title' => 'Annual report'], $post->getAttributes());
    }
}
