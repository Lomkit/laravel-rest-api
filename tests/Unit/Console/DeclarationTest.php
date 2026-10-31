<?php

namespace Lomkit\Rest\Tests\Unit\Console;

use Illuminate\Validation\Rule;
use Lomkit\Rest\Console\Generation\Declaration;
use Lomkit\Rest\Console\Generation\ImportSet;
use Lomkit\Rest\Fields\DateTime;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Relations\BelongsToMany;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DeclarationTest extends TestCase
{
    #[Test]
    public function it_keeps_a_single_call_on_the_line_of_make(): void
    {
        $declaration = Declaration::make(Text::class, "'title'")->with('nullable()');

        $this->assertSame("Text::make('title')->nullable(),", $declaration->render($this->imports()));
    }

    #[Test]
    public function it_writes_one_call_per_line_after_two_calls(): void
    {
        $declaration = Declaration::make(Text::class, "'title'")
            ->with('nullable()')
            ->with('readonly()');

        $this->assertSame("Text::make('title')\n    ->nullable()\n    ->readonly(),", $declaration->render($this->imports()));
    }

    #[Test]
    public function it_lists_the_classes_that_its_calls_use(): void
    {
        $declaration = Declaration::make(Text::class, "'slug'")->with("creationRules(Rule::unique(Post::class, 'slug'))", Rule::class);

        $this->assertSame([Text::class, Rule::class], $declaration->classes());
    }

    #[Test]
    public function it_writes_a_list_argument_one_item_per_line(): void
    {
        $declaration = Declaration::make(BelongsToMany::class, "'tags'", 'TagResource::class')->withList('pivotFields', [
            Declaration::make(Integer::class, "'position'"),
            Declaration::make(DateTime::class, "'created_at'")
                ->with('nullable()')
                ->with('readonly()'),
        ]);

        $this->assertSame(
            "BelongsToMany::make('tags', TagResource::class)->pivotFields([\n"
            ."    Integer::make('position'),\n"
            ."    DateTime::make('created_at')\n"
            ."        ->nullable()\n"
            ."        ->readonly(),\n"
            .']),',
            $declaration->render($this->imports()),
        );
    }

    #[Test]
    public function it_writes_a_list_argument_on_its_own_line_after_another_call(): void
    {
        $declaration = Declaration::make(BelongsToMany::class, "'tags'", 'TagResource::class')
            ->with('requiredOnCreation()')
            ->withList('pivotFields', [Declaration::make(Integer::class, "'position'")]);

        $this->assertSame(
            "BelongsToMany::make('tags', TagResource::class)\n"
            ."    ->requiredOnCreation()\n"
            ."    ->pivotFields([\n"
            ."        Integer::make('position'),\n"
            .'    ]),',
            $declaration->render($this->imports()),
        );
    }

    #[Test]
    public function it_lists_the_classes_of_its_arguments_and_of_its_list_items(): void
    {
        $declaration = Declaration::make(BelongsToMany::class, "'tags'", 'TagResource::class')
            ->using('App\Rest\Resources\Admin\TagResource')
            ->withList('pivotFields', [Declaration::make(Text::class, "'role'")->with("creationRules(Rule::unique(Post::class, 'role'))", Rule::class)]);

        $this->assertSame([BelongsToMany::class, 'App\Rest\Resources\Admin\TagResource', Text::class, Rule::class], $declaration->classes());
    }

    private function imports(): ImportSet
    {
        return ImportSet::forResource('App\Rest\Resources\PostResource', 'App\Rest\Resources\Resource');
    }
}
