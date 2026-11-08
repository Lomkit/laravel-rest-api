<?php

namespace Lomkit\Rest\Tests\Unit\Search;

use Lomkit\Rest\Exceptions\InvalidResourceException;
use Lomkit\Rest\Search\GateVocabulary;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class GateVocabularyTest extends TestCase
{
    private const string OWNER = 'App\Rest\Resources\TicketResource::gates()';

    #[Test]
    public function it_lists_the_built_in_gates_then_the_declared_ones(): void
    {
        $this->assertSame(
            ['view', 'create', 'update', 'delete', 'restore', 'forceDelete', 'escalate', 'reassignTeam'],
            GateVocabulary::of(['escalate', 'reassignTeam'], self::OWNER, true)->names(),
        );
    }

    #[Test]
    public function it_leaves_out_restore_and_force_delete_without_soft_deletes(): void
    {
        $gates = GateVocabulary::of(['escalate'], self::OWNER, false);

        $this->assertSame(['view', 'create', 'update', 'delete', 'escalate'], $gates->names());
        $this->assertNull($gates->find('restore'));
        $this->assertNull($gates->find('forceDelete'));
    }

    #[Test]
    public function it_refuses_a_declared_restore_gate_without_soft_deletes(): void
    {
        $this->expectExceptionObject(new InvalidResourceException(self::OWNER.' declares the gate [restore] at index 0. A declared gate is the camelCase name of a policy method, listed once, other than viewAny and the built-in gates view, create, update, delete, restore and forceDelete.'));

        GateVocabulary::of(['restore'], self::OWNER, false);
    }

    #[Test]
    #[DataProvider('names')]
    public function it_finds_a_gate_by_its_name(mixed $name, ?string $key): void
    {
        $gate = GateVocabulary::of(['escalate', 'reassignTeam'], self::OWNER, true)->find($name);

        $this->assertSame($key, $gate?->key());
    }

    /** @return array<string, array{mixed, ?string}> */
    public static function names(): array
    {
        return [
            'a built-in gate' => ['create', 'authorized_to_create'],
            'a declared gate' => ['reassignTeam', 'authorized_to_reassign_team'],
            'viewAny' => ['viewAny', null],
            'another case' => ['Escalate', null],
            'a gate that is not declared' => ['archive', null],
            'null' => [null, null],
            'an integer' => [7, null],
        ];
    }

    /** @param array<mixed> $declared */
    #[Test]
    #[DataProvider('invalidDeclarations')]
    public function it_refuses_an_invalid_declaration(array $declared, string $message): void
    {
        $this->expectExceptionObject(new InvalidResourceException(self::OWNER.$message));

        GateVocabulary::of($declared, self::OWNER, true);
    }

    /** @return array<string, array{array<mixed>, string}> */
    public static function invalidDeclarations(): array
    {
        $rule = ' A declared gate is the camelCase name of a policy method, listed once, other than viewAny and the built-in gates view, create, update, delete, restore and forceDelete.';

        return [
            'a snake case name' => [['escalate', 'reassign_team'], ' declares the gate [reassign_team] at index 1.'.$rule],
            'a dash' => [['reassign-team'], ' declares the gate [reassign-team] at index 0.'.$rule],
            'a capital first letter' => [['Escalate'], ' declares the gate [Escalate] at index 0.'.$rule],
            'an empty name' => [[''], ' declares the gate [] at index 0.'.$rule],
            'viewAny' => [['viewAny'], ' declares the gate [viewAny] at index 0.'.$rule],
            'a built-in gate' => [['escalate', 'forceDelete'], ' declares the gate [forceDelete] at index 1.'.$rule],
            'a repeated name' => [['escalate', 'escalate'], ' declares the gate [escalate] at index 1.'.$rule],
            'null' => [['escalate', null], ' returned null at index 1. Return the names of policy methods.'],
            'an integer' => [[7], ' returned int at index 0. Return the names of policy methods.'],
        ];
    }
}
