<?php

namespace Lomkit\Rest\Tests\Unit\Search;

use Lomkit\Rest\Exceptions\InvalidAuthorizationExtensionException;
use Lomkit\Rest\Exceptions\InvalidResourceException;
use Lomkit\Rest\Search\DeclaredGateList;
use Lomkit\Rest\Search\GateVocabulary;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class GateVocabularyTest extends TestCase
{
    private const string OWNER = 'App\Rest\Resources\TicketResource::gates()';

    private const string RESOURCE = 'App\Rest\Resources\TicketResource';

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

    #[Test]
    public function it_lists_extension_gates_after_the_resource_gates_once(): void
    {
        $gates = GateVocabulary::of(['escalate', 'archive'], self::OWNER, false, [self::extension('acl', ['export', 'archive', 'share']), self::extension('shares', ['share', 'escalate', 'publish'])]);

        $this->assertSame(['view', 'create', 'update', 'delete', 'escalate', 'archive', 'export', 'share', 'publish'], $gates->names());
        $this->assertSame('authorized_to_share', $gates->find('share')?->key());
    }

    #[Test]
    public function it_refuses_a_gate_listed_twice_by_one_extension(): void
    {
        $this->expectExceptionObject(InvalidAuthorizationExtensionException::invalidGate('acl', 'Vendor\Acl\AclExtension', self::RESOURCE, 1, 'share'));

        GateVocabulary::of(['share'], self::OWNER, true, [self::extension('acl', ['share', 'share'])]);
    }

    #[Test]
    public function it_refuses_a_resource_gate_through_the_resource_list_when_extensions_declare_gates(): void
    {
        $this->expectExceptionObject(InvalidResourceException::invalidGate(self::OWNER, 0, 'Share'));

        GateVocabulary::of(['Share'], self::OWNER, true, [self::extension('acl', ['Share'])]);
    }

    /** @param array<mixed> $declared */
    #[Test]
    #[DataProvider('invalidExtensionDeclarations')]
    public function it_refuses_an_invalid_name_through_the_extension_that_declares_it(array $declared, int $index, mixed $name): void
    {
        $this->expectExceptionObject(InvalidAuthorizationExtensionException::invalidGate('shares', 'Vendor\Shares\SharesExtension', self::RESOURCE, $index, $name));

        GateVocabulary::of(['escalate'], self::OWNER, true, [self::extension('acl', ['export']), self::extension('shares', $declared)]);
    }

    #[Test]
    public function it_names_the_type_that_an_extension_returns_instead_of_a_gate_name(): void
    {
        try {
            GateVocabulary::of(['escalate'], self::OWNER, true, [self::extension('shares', [7])]);
            $this->fail('The integer was not refused.');
        } catch (InvalidAuthorizationExtensionException $exception) {
            $this->assertSame('The [shares] extension Vendor\Shares\SharesExtension returns int at index 0 from gates() for App\Rest\Resources\TicketResource. Return the names of policy methods.', $exception->getMessage());
        }
    }

    /** @return array<string, array{array<mixed>, int, mixed}> */
    public static function invalidExtensionDeclarations(): array
    {
        return [
            'a snake case name' => [['escalate', 'reassign_team'], 1, 'reassign_team'],
            'a capital first letter' => [['Escalate'], 0, 'Escalate'],
            'viewAny' => [['viewAny'], 0, 'viewAny'],
            'a built-in gate' => [['escalate', 'forceDelete'], 1, 'forceDelete'],
            'a repeated name' => [['escalate', 'escalate'], 1, 'escalate'],
            'null' => [['escalate', null], 1, null],
            'an integer' => [[7], 0, 7],
        ];
    }

    /** @param array<mixed> $names */
    private static function extension(string $name, array $names): DeclaredGateList
    {
        $class = 'Vendor\\'.ucfirst($name).'\\'.ucfirst($name).'Extension';

        return new DeclaredGateList($names, static fn (int $index, mixed $gate): InvalidAuthorizationExtensionException => InvalidAuthorizationExtensionException::invalidGate($name, $class, self::RESOURCE, $index, $gate));
    }
}
