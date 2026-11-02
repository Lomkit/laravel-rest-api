<?php

namespace Lomkit\Rest\Tests\Feature\Mutations\Stubs;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Mutations\Contextual;
use Lomkit\Rest\Mutations\MutationContext;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;

class ContextualPostResource extends PostResource
{
    #[\Override]
    public function rules(RestRequest $request): array
    {
        return [
            'title' => [Contextual::rule(fn (MutationContext $mutation): Unique => Rule::unique(Post::class, 'title')->ignore($mutation->key))],
            'views' => [new RecordsMutation],
        ];
    }
}
