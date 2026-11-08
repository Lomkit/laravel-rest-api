<?php

namespace Lomkit\Rest\Tests\Feature\Details;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Exceptions\InvalidResourceException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Details\Stubs\KeyedArtworkController;
use Lomkit\Rest\Tests\Feature\Details\Stubs\MisdeclaredMorphArtworkController;
use Lomkit\Rest\Tests\Feature\Details\Stubs\MisdeclaredMorphArtworkResource;
use Lomkit\Rest\Tests\Fixtures\Gallery\Artist;
use Lomkit\Rest\Tests\Fixtures\Gallery\Collector;
use Lomkit\Rest\Tests\Fixtures\Gallery\CollectorResource;
use Lomkit\Rest\Tests\Fixtures\Gallery\Gallery;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class ArtworkDetailsTest extends PackageTestCase
{
    use AssertsDetailsPayload;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Relation::morphMap([], false);
    }

    #[\Override]
    protected function tearDown(): void
    {
        Relation::morphMap([], false);

        parent::tearDown();
    }

    #[\Override]
    protected function fixtures(): array
    {
        return [new Gallery];
    }

    #[Test]
    public function it_describes_every_part_of_a_resource(): void
    {
        $this->assertDetailsPayload('artworks', $this->getJson('/api/artworks?with='.self::EVERY_PART));
    }

    #[Test]
    public function it_keys_the_morph_types_by_the_morph_map_alias(): void
    {
        Relation::morphMap(['collector' => Collector::class], false);

        $this->getJson('/api/artworks?with=relations')
            ->assertOk()
            ->assertJsonPath('data.relations.2.types', ['collector' => CollectorResource::class])
            ->assertJsonPath('data.relations.2.resource', CollectorResource::class);
    }

    #[Test]
    public function it_encodes_the_morph_types_as_an_object_when_the_alias_is_zero(): void
    {
        Relation::morphMap([1 => Artist::class, 0 => Collector::class], false);

        $content = $this->getJson('/api/artworks?with=relations')
            ->assertOk()
            ->getContent();

        $this->assertSame('{"0":'.json_encode(CollectorResource::class).'}', json_encode(json_decode($content, flags: JSON_THROW_ON_ERROR)->data->relations[2]->types));
    }

    #[Test]
    public function it_lists_the_declarations_that_a_resource_returns_with_keys(): void
    {
        Route::prefix('api')->group(fn () => Rest::resource('keyed-artworks', KeyedArtworkController::class)->register());

        $data = $this->getJson('/api/keyed-artworks?with=actions,instructions,relations')
            ->assertOk()
            ->json('data');

        $this->assertSame(['retire-artworks'], array_map(fn (array $action): string => $action['uriKey'], $data['actions']));
        $this->assertSame(['by-medium'], array_map(fn (array $instruction): string => $instruction['uriKey'], $data['instructions']));
        $this->assertSame(['artist'], array_map(fn (array $relation): string => $relation['relation'], $data['relations']));
        $this->assertTrue(array_is_list($data['actions']));
        $this->assertTrue(array_is_list($data['instructions']));
        $this->assertTrue(array_is_list($data['relations']));
    }

    #[Test]
    public function it_refuses_a_morph_to_whose_model_method_is_not_a_morph_to(): void
    {
        Route::prefix('api')->group(fn () => Rest::resource('misdeclared-artworks', MisdeclaredMorphArtworkController::class)->register());

        $this->withoutExceptionHandling();

        $this->expectExceptionObject(InvalidResourceException::notAMorphTo(MisdeclaredMorphArtworkResource::class, 'artist'));

        $this->getJson('/api/misdeclared-artworks?with=relations');
    }
}
