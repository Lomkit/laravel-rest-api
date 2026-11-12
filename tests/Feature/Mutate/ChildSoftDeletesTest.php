<?php

namespace Lomkit\Rest\Tests\Feature\Mutate;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Tests\Fixtures\Festival\Festival;
use Lomkit\Rest\Tests\Fixtures\Festival\Show;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class ChildSoftDeletesTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Festival];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-03-01 09:00:00');
        DB::table('stages')->insert([['code' => 'main', 'name' => 'Main stage'], ['code' => 'side', 'name' => 'Side stage']]);
        DB::table('shows')->insert([
            ['id' => 1, 'stage_code' => 'main', 'title' => 'Opening night', 'starts_on' => '2026-07-01', 'status' => 'on_sale'],
            ['id' => 2, 'stage_code' => 'main', 'title' => 'Second night', 'starts_on' => '2026-07-02', 'status' => 'on_sale'],
            ['id' => 3, 'stage_code' => 'side', 'title' => 'Closing night', 'starts_on' => '2026-07-03', 'status' => 'planned'],
        ]);
    }

    #[Test]
    public function it_soft_deletes_a_child_whose_model_uses_soft_deletes(): void
    {
        $response = $this->postJson('/api/stages/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => 'main', 'relations' => ['shows' => [['operation' => 'delete', 'key' => [1]]]]],
        ]]);

        $response->assertOk();
        $response->assertExactJson(['operations' => [['operation' => 'update', 'key' => 'main', 'relations' => ['shows' => [['operation' => 'delete', 'key' => [1]]]]]]]);
        $this->assertSame(
            ['1 main 2026-03-01 09:00:00', '2 main -', '3 side -'],
            Show::withTrashed()
                ->orderBy('id')
                ->get()
                ->map(fn (Show $show): string => $show->getKey().' '.$show->getAttribute('stage_code').' '.($show->getRawOriginal('deleted_at') ?? '-'))
                ->all(),
        );
    }

    #[Test]
    public function it_refuses_a_child_that_is_already_trashed(): void
    {
        Show::query()
            ->whereKey(2)
            ->delete();

        $response = $this->postJson('/api/stages/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => 'main', 'relations' => ['shows' => [['operation' => 'delete', 'key' => [2]]]]],
        ]]);

        $response->assertUnprocessable();
        $response->assertExactJson(['message' => 'The selected mutate.0.relations.shows.0.key is invalid.', 'errors' => ['mutate.0.relations.shows.0.key' => ['The selected mutate.0.relations.shows.0.key is invalid.']]]);
    }

    #[Test]
    public function it_leaves_the_trashed_children_out_of_the_check_of_a_sync(): void
    {
        DB::table('shows')->insert(['id' => 4, 'stage_code' => 'main', 'title' => 'Rained out', 'starts_on' => '2026-06-30', 'status' => 'planned', 'deleted_at' => '2026-02-01 08:00:00']);

        $response = $this->postJson('/api/stages/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => 'main', 'relations' => ['shows' => [['operation' => 'sync', 'key' => [1]], ['operation' => 'delete', 'key' => [2]]]]],
        ]]);

        $response->assertOk();
        $response->assertExactJson(['operations' => [['operation' => 'update', 'key' => 'main', 'relations' => ['shows' => [['operation' => 'sync', 'key' => [1]], ['operation' => 'delete', 'key' => [2]]]]]]]);
        $this->assertSame(
            ['1 main -', '2 main 2026-03-01 09:00:00', '3 side -', '4 main 2026-02-01 08:00:00'],
            Show::withTrashed()
                ->orderBy('id')
                ->get()
                ->map(fn (Show $show): string => $show->getKey().' '.$show->getAttribute('stage_code').' '.($show->getRawOriginal('deleted_at') ?? '-'))
                ->all(),
        );
    }
}
