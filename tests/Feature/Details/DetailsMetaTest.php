<?php

namespace Lomkit\Rest\Tests\Feature\Details;

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Details\Stubs\MetaArtworkController;
use Lomkit\Rest\Tests\Fixtures\Gallery\Gallery;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class DetailsMetaTest extends PackageTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Route::prefix('api')->group(fn () => Rest::resource('meta-artworks', MetaArtworkController::class)->register());
    }

    #[\Override]
    protected function fixtures(): array
    {
        return [new Gallery];
    }

    #[Test]
    public function it_answers_an_empty_meta_object_for_a_resource_without_details_meta(): void
    {
        $content = $this->getJson('/api/artworks')
            ->assertOk()
            ->getContent();

        $this->assertSame('{}', json_encode(json_decode((string) $content, flags: JSON_THROW_ON_ERROR)->data->meta));
    }

    #[Test]
    public function it_answers_the_details_meta_of_the_resource_last(): void
    {
        $this->actingAs(new GenericUser(['id' => 7, 'name' => 'Ada']));

        $content = $this->getJson('/api/meta-artworks')
            ->assertOk()
            ->getContent();
        $data = json_decode((string) $content, flags: JSON_THROW_ON_ERROR)->data;

        $this->assertSame('{"label":"Artworks","default_sort":{"field":"title","direction":"desc"},"help":[],"curator":"Ada"}', json_encode($data->meta));
        $this->assertSame('meta', array_key_last(get_object_vars($data)));
    }

    #[Test]
    public function it_computes_the_details_meta_for_the_user_of_each_request(): void
    {
        $this->actingAs(new GenericUser(['id' => 7, 'name' => 'Ada']));
        $this->getJson('/api/meta-artworks')->assertJsonPath('data.meta.curator', 'Ada');

        $this->actingAs(new GenericUser(['id' => 8, 'name' => 'Grace']));
        $this->getJson('/api/meta-artworks')->assertJsonPath('data.meta.curator', 'Grace');
    }
}
