<?php

namespace Lomkit\Rest\Tests\Unit\Search;

use Lomkit\Rest\Authorization\Ability;
use Lomkit\Rest\Search\GateAbility;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class GateAbilityTest extends TestCase
{
    #[Test]
    #[DataProvider('abilities')]
    public function it_names_the_answer_of_each_ability(Ability|string $ability, string $name, string $key, bool $classLevel): void
    {
        $gate = new GateAbility($ability);

        $this->assertSame([$name, $key, $classLevel], [$gate->name(), $gate->key(), $gate->isClassLevel()]);
    }

    /** @return array<string, array{Ability|string, string, string, bool}> */
    public static function abilities(): array
    {
        return [
            'view' => [Ability::View, 'view', 'authorized_to_view', false],
            'create' => [Ability::Create, 'create', 'authorized_to_create', true],
            'update' => [Ability::Update, 'update', 'authorized_to_update', false],
            'delete' => [Ability::Delete, 'delete', 'authorized_to_delete', false],
            'restore' => [Ability::Restore, 'restore', 'authorized_to_restore', false],
            'force delete' => [Ability::ForceDelete, 'forceDelete', 'authorized_to_force_delete', false],
            'a declared gate' => ['reassignTeam', 'reassignTeam', 'authorized_to_reassign_team', false],
            'a built-in gate given by its name' => ['create', 'create', 'authorized_to_create', true],
        ];
    }

    #[Test]
    public function it_holds_the_ability_of_a_built_in_name(): void
    {
        $this->assertSame([Ability::ForceDelete, 'reassignTeam'], [(new GateAbility('forceDelete'))->ability, (new GateAbility('reassignTeam'))->ability]);
    }
}
