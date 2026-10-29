<?php

namespace Lomkit\Rest\Tests\Unit\Scopes;

use Lomkit\Rest\Exceptions\InvalidFieldException;
use Lomkit\Rest\Fields\Date;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Scopes\Scope;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ScopeTest extends TestCase
{
    #[Test]
    public function it_declares_a_scope_without_fields_by_default(): void
    {
        $scope = Scope::make('published');

        $this->assertSame('published', $scope->name);
        $this->assertSame([], $scope->getFields()->names());
        $this->assertSame([], $scope->getMeta());
    }

    #[Test]
    public function it_keeps_the_fields_in_declared_order(): void
    {
        $scope = Scope::make('between')->fields([Date::make('from'), Date::make('to')]);

        $this->assertSame(['from', 'to'], $scope->getFields()->names());
        $this->assertSame($scope->getFields(), $scope->getFields());
    }

    #[Test]
    public function it_merges_the_meta_with_later_keys_winning(): void
    {
        $scope = Scope::make('popular')->meta(['label' => 'Popular', 'icon' => 'star'])->meta(['label' => 'Most read']);

        $this->assertSame(['label' => 'Most read', 'icon' => 'star'], $scope->getMeta());
    }

    #[Test]
    public function it_serializes_its_name_fields_and_meta(): void
    {
        $scope = Scope::make('popular')
            ->fields([Integer::make('min_views')->default(100)->rules('min:0')])
            ->meta(['label' => 'Popular']);

        $this->assertSame([
            'name' => 'popular',
            'fields' => [
                ['name' => 'min_views', 'type' => 'integer', 'nullable' => false, 'default' => 100, 'rules' => ['integer', 'min:0'], 'meta' => []],
            ],
            'meta' => ['label' => 'Popular'],
        ], $scope->jsonSerialize());
    }

    #[Test]
    public function it_names_the_scope_when_a_field_is_not_a_field_object(): void
    {
        $this->expectException(InvalidFieldException::class);
        $this->expectExceptionMessage("Scope::make('publishedSince')->fields() returned a string at index 0 ('since'). Return Field objects, for example Text::make('since').");

        Scope::make('publishedSince')->fields(['since']);
    }

    #[Test]
    public function it_names_the_scope_when_a_field_is_declared_twice(): void
    {
        $this->expectException(InvalidFieldException::class);
        $this->expectExceptionMessage("Scope::make('between')->fields() declares the field [from] twice.");

        Scope::make('between')->fields([Date::make('from'), Date::make('from')]);
    }

    #[Test]
    public function it_names_the_scope_when_a_field_declares_write_rules(): void
    {
        $this->expectException(InvalidFieldException::class);
        $this->expectExceptionMessage("Scope::make('between')->fields() declares creation or update rules on the input field [from]. Input fields take rules() only.");

        Scope::make('between')->fields([Date::make('from')->creationRules('required')]);
    }
}
