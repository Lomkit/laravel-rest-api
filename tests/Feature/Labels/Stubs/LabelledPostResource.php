<?php

namespace Lomkit\Rest\Tests\Feature\Labels\Stubs;

use Lomkit\Rest\Fields\Date;
use Lomkit\Rest\Fields\DateTime;
use Lomkit\Rest\Fields\Enum;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Scopes\Scope;
use Lomkit\Rest\Tests\Fixtures\Publishing\HighlightPosts;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;
use Lomkit\Rest\Tests\Fixtures\Publishing\PublishPosts;

class LabelledPostResource extends PostResource
{
    #[\Override]
    public function fields(): array
    {
        return [
            ID::make('id'),
            Text::make('title')
                ->rules('max:60')
                ->creationRules('required'),
            Enum::make('status')
                ->label('post status')
                ->creationRules('required')
                ->messages(['enum' => 'Pick a known :attribute.']),
            Date::make('published_on')
                ->label('publication date')
                ->creationRules('required'),
            DateTime::make('published_at')
                ->label('publication time')
                ->nullable()
                ->rules('after_or_equal:published_on'),
        ];
    }

    #[\Override]
    public function scopes(): array
    {
        return [
            Scope::make('publishedSince')->fields([Date::make('since')->label('start date')]),
            Scope::make('popular')->fields([Integer::make('min_views')->default(100)]),
        ];
    }

    #[\Override]
    public function instructions(): array
    {
        return [HighlightPosts::make(), LabelledHighlightPosts::make()];
    }

    #[\Override]
    public function actions(): array
    {
        return [LabelledChangeStatus::make(), PublishPosts::make()];
    }
}
