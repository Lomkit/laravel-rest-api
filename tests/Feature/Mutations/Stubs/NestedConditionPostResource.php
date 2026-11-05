<?php

namespace Lomkit\Rest\Tests\Feature\Mutations\Stubs;

use Illuminate\Support\Fluent;
use Illuminate\Validation\ConditionalRules;
use Illuminate\Validation\Rule;
use Lomkit\Rest\Mutations\Contextual;
use Lomkit\Rest\Mutations\MutationContext;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;

class NestedConditionPostResource extends PostResource
{
    #[\Override]
    public function rules(): array
    {
        $featured = fn (Fluent $attributes): bool => $attributes->get('featured') === true;

        return [
            'summary' => [Contextual::rule(fn (MutationContext $mutation): ConditionalRules => Rule::when($featured, ['required']))],
            'rating' => [Rule::when($featured, [Contextual::rule(fn (MutationContext $mutation): string => 'required|max:'.$mutation->depth)])],
        ];
    }
}
