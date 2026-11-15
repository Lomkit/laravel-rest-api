<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Illuminate\Database\Query\Builder;
use Lomkit\Rest\Extensions\Contexts\AugmentationContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\AugmentsQueries;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;

final class FlagExtension extends Extension implements AugmentsQueries
{
    #[\Override]
    public static function name(): string
    {
        return 'flag';
    }

    #[\Override]
    public function augment(AugmentationContext $context): void
    {
        AugmentationLog::$gates[] = class_basename($context->resource).' ['.implode(', ', $context->gates).']';
        AugmentationLog::$aliases[] = $context->select('marker', static function (Builder $query): void {
            $query->selectRaw("'seen'");
        });

        if (! $context->resource::newModel() instanceof Author) {
            return;
        }

        $context->column('api_token');
        AugmentationLog::$aliases[] = $context->select('post_total', Post::query()->selectRaw('count(*)')->whereColumn('posts.author_id', 'authors.id'));
        $context->with('drafts');
    }
}
