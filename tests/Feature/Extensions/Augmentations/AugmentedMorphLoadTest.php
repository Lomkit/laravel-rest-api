<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Augmentations;

use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Exceptions\InvalidQueryExtensionException;
use Lomkit\Rest\Extensions\Contexts\AugmentationContext;
use Lomkit\Rest\Tests\Concerns\CapturesQueries;
use Lomkit\Rest\Tests\Concerns\ResetsExtensionStubs;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\FirstAugmentation;
use Lomkit\Rest\Tests\Feature\MorphTargets\MorphTargetsTestCase;
use Lomkit\Rest\Tests\Fixtures\Library\LabelResource;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

#[WithConfig('rest.extensions', [FirstAugmentation::class])]
class AugmentedMorphLoadTest extends MorphTargetsTestCase
{
    use CapturesQueries;
    use ResetsExtensionStubs;

    #[Test]
    public function it_eager_loads_a_morph_to_relation(): void
    {
        FirstAugmentation::$augment = static fn (AugmentationContext $context): mixed => $context->with('labelable');
        $this->captureQueries();

        $response = $this->postJson('/api/labels/search', ['search' => ['selects' => [['field' => 'text']], 'sorts' => [['field' => 'id']]]]);

        $response->assertOk()->assertJsonPath('data.*.text', ['classic', 'shelved', 'novel', 'due', 'loose']);
        $this->assertSame([
            ['select labels.text, labels.id, labels.labelable_type, labels.labelable_id from labels order by labels.id asc limit 50 offset 0', []],
            ['select * from books where books.id in (1, 2)', []],
            ['select * from shelves where shelves.id in (1)', []],
            ['select * from loans where loans.id in (1)', []],
        ], array_values(array_filter($this->statements, static fn (array $statement): bool => ! str_contains($statement[0], 'count(*)'))));
    }

    #[Test]
    #[DataProvider('pathsPastMorphTo')]
    public function it_refuses_a_load_past_a_morph_to_relation(string $relation): void
    {
        FirstAugmentation::$augment = static fn (AugmentationContext $context): mixed => $context->with($relation);
        $this->withoutExceptionHandling();
        $before = DB::table('books')->count();

        $this->expectExceptionObject(InvalidQueryExtensionException::loadThroughMorphTo('first', FirstAugmentation::class, $relation, 'labelable', LabelResource::class));

        try {
            $this->postJson('/api/labels/search', ['search' => []]);
        } finally {
            $this->assertSame($before, DB::table('books')->count());
        }
    }

    /** @return array<string, array{string}> */
    public static function pathsPastMorphTo(): array
    {
        return [
            'a relation of a target' => ['labelable.labels'],
            'a method of a target' => ['labelable.save'],
        ];
    }
}
