<?php

namespace Lomkit\Rest\Tests\Feature\Relations\Persisters;

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Lomkit\Rest\Exceptions\PivotPersisterException;
use Lomkit\Rest\Tests\Feature\Relations\Persisters\Stubs\Memberships\Member;
use Lomkit\Rest\Tests\Feature\Relations\Persisters\Stubs\Memberships\Memberships;
use Lomkit\Rest\Tests\Feature\Relations\Persisters\Stubs\Memberships\Project;
use Lomkit\Rest\Tests\Feature\Relations\Persisters\Stubs\Memberships\ProjectMemberships;
use Lomkit\Rest\Tests\Feature\Relations\Persisters\Stubs\Memberships\ProjectResource;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class WithdrawnMembersTest extends PackageTestCase
{
    private const string NOW = '2026-04-01 10:00:00';

    private const string EARLIER = '2026-02-01 10:00:00';

    private Project $atlas;

    private Member $ada;

    private Member $grace;

    private Member $linus;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Memberships];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(self::NOW);
        $this->atlas = Project::create(['name' => 'Atlas']);
        $this->ada = Member::create(['name' => 'Ada']);
        $this->grace = Member::create(['name' => 'Grace']);
        $this->linus = Member::create(['name' => 'Linus']);
    }

    #[Test]
    public function it_keeps_the_row_of_a_detached_member(): void
    {
        $this->join($this->ada, 'lead', null);

        $response = $this->mutate('members', ['operation' => 'detach', 'key' => [$this->ada->id]]);

        $response->assertOk();
        $this->assertSame([['Ada', 'lead', self::NOW]], $this->rows());
        $this->assertSame([], $this->atlas->members()->pluck('name')->all());
    }

    #[Test]
    public function it_attaches_a_withdrawn_member_again(): void
    {
        $this->join($this->ada, 'lead', self::EARLIER);

        $response = $this->mutate('members', ['operation' => 'attach', 'key' => [$this->ada->id], 'pivot' => ['role' => 'reviewer']]);

        $response->assertOk()->assertExactJson(['operations' => [[
            'operation' => 'update',
            'key' => $this->atlas->id,
            'relations' => ['members' => [['operation' => 'attach', 'key' => [$this->ada->id]]]],
        ]]]);
        $this->assertSame([['Ada', 'reviewer', null]], $this->rows());
    }

    #[Test]
    public function it_syncs_over_withdrawn_members(): void
    {
        $this->join($this->ada, 'lead', null);
        $this->join($this->grace, 'reviewer', self::EARLIER);

        $response = $this->mutate('members', ['operation' => 'sync', 'key' => [$this->grace->id, $this->linus->id], 'pivot' => ['role' => 'writer']]);

        $response->assertOk();
        $this->assertSame([['Ada', 'lead', self::NOW], ['Grace', 'writer', null], ['Linus', 'writer', null]], $this->rows());
        $this->assertSame(['Grace', 'Linus'], $this->atlas->members()->orderBy('name')->pluck('name')->all());
    }

    #[Test]
    public function it_refuses_the_withdrawal_persister_on_a_relation_without_its_pivot_condition(): void
    {
        $this->join($this->ada, 'lead', null);
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(PivotPersisterException::undetached(ProjectMemberships::class, ProjectResource::class, 'everyone', [$this->ada->id]));

        $this->mutate('everyone', ['operation' => 'detach', 'key' => [$this->ada->id]]);
    }

    #[Test]
    public function it_leaves_attached_on_toggle_a_row_that_the_relation_hides(): void
    {
        $this->join($this->grace, 'reviewer', null);

        $response = $this->mutate('staff', ['operation' => 'toggle', 'key' => [$this->grace->id]]);

        $response->assertOk();
        $this->assertSame([['Grace', 'reviewer', null]], $this->rows());
    }

    private function join(Member $member, string $role, ?string $withdrawnAt): void
    {
        DB::table('member_project')->insert(['project_id' => $this->atlas->id, 'member_id' => $member->id, 'role' => $role, 'withdrawn_at' => $withdrawnAt]);
    }

    /** @param array<string, mixed> $entry */
    private function mutate(string $relation, array $entry): TestResponse
    {
        return $this->postJson('/projects/mutate', ['mutate' => [[
            'operation' => 'update',
            'key' => $this->atlas->id,
            'relations' => [$relation => [$entry]],
        ]]]);
    }

    /** @return list<array{string, ?string, ?string}> */
    private function rows(): array
    {
        return DB::table('member_project')
            ->join('members', 'members.id', '=', 'member_project.member_id')
            ->where('project_id', $this->atlas->id)
            ->orderBy('members.name')
            ->get(['members.name', 'member_project.role', 'member_project.withdrawn_at'])
            ->map(fn (object $row): array => [$row->name, $row->role, $row->withdrawn_at === null ? null : substr((string) $row->withdrawn_at, 0, 19)])
            ->all();
    }
}
