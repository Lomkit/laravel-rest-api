<?php

namespace Lomkit\Rest\Tests\Feature\Search\Outside;

use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\LoadedArtworkResource;
use Lomkit\Rest\Tests\Fixtures\Gallery\Artist;
use Lomkit\Rest\Tests\Fixtures\Gallery\Artwork;
use Lomkit\Rest\Tests\Fixtures\Gallery\Gallery;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class LazyLoadedRowsTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Gallery];
    }

    #[Test]
    public function it_reads_rows_that_the_instruction_of_the_search_decorated(): void
    {
        Artist::create(['id' => 1, 'name' => 'Ada']);
        Artwork::create(['id' => 1, 'artist_id' => 1, 'title' => 'Sea', 'medium' => 'oil']);
        Artwork::create(['id' => 2, 'artist_id' => 1, 'title' => 'Dawn', 'medium' => 'ink']);
        Artwork::create(['id' => 3, 'artist_id' => 1, 'title' => 'Field', 'medium' => 'watercolor']);

        $rows = Rest::search(LoadedArtworkResource::class)
            ->as(null)
            ->payload(['instructions' => [['name' => 'mark-loaded-artworks']], 'selects' => [['field' => 'id'], ['field' => 'title']]])
            ->lazy(chunk: 2);

        $this->assertSame([['id' => 3, 'title' => 'Field (loaded)'], ['id' => 2, 'title' => 'Dawn (loaded)'], ['id' => 1, 'title' => 'Sea (loaded)']], $rows->all());
    }
}
