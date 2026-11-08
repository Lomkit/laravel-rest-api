<?php

namespace Lomkit\Rest\Tests\Feature\Details\Stubs;

use Lomkit\Rest\Tests\Fixtures\Gallery\ArtworkResource;

class MetaArtworkResource extends ArtworkResource
{
    #[\Override]
    public function detailsMeta(): array
    {
        return [
            'label' => 'Artworks',
            'default_sort' => ['field' => 'title', 'direction' => 'desc'],
            'help' => [],
            'curator' => $this->request()->user()?->name,
        ];
    }
}
