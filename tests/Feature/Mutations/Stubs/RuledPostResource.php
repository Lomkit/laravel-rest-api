<?php

namespace Lomkit\Rest\Tests\Feature\Mutations\Stubs;

use Illuminate\Validation\Rule;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;

class RuledPostResource extends PostResource
{
    #[\Override]
    public function rules(): array
    {
        return [
            'title' => 'string|min:3',
            'metadata.source' => 'string',
            'metadata.tags.*' => 'string|max:5',
        ];
    }

    #[\Override]
    public function createRules(): array
    {
        return [
            'title' => [Rule::unique(Post::class, 'title')],
            'summary' => 'required_if:featured,true',
        ];
    }

    #[\Override]
    public function updateRules(): array
    {
        return [
            'views' => 'integer|max:1000000',
        ];
    }
}
