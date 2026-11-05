<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Relations\MorphTo;
use Lomkit\Rest\Tests\Fixtures\Library\BookResource;
use Lomkit\Rest\Tests\Fixtures\Library\LabelResource;

class BookLabelResource extends LabelResource
{
    #[\Override]
    public function relations(RestRequest $request): array
    {
        return [MorphTo::make('labelable', BookResource::class)];
    }
}
