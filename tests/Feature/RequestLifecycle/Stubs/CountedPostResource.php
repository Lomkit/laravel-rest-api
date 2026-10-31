<?php

namespace Lomkit\Rest\Tests\Feature\RequestLifecycle\Stubs;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;

class CountedPostResource extends PostResource
{
    #[\Override]
    public function fields(RestRequest $request): array
    {
        $this->evaluated('fields', $request);

        if ($request->user()?->getAttribute('is_staff')) {
            return [ID::make('id'), Text::make('title'), Integer::make('views')];
        }

        return [ID::make('id'), Text::make('title')];
    }

    #[\Override]
    public function scopes(RestRequest $request): array
    {
        $this->evaluated('scopes', $request);

        return parent::scopes($request);
    }

    #[\Override]
    public function relations(RestRequest $request): array
    {
        $this->evaluated('relations', $request);

        return parent::relations($request);
    }

    #[\Override]
    public function instructions(RestRequest $request): array
    {
        $this->evaluated('instructions', $request);

        return parent::instructions($request);
    }

    #[\Override]
    public function actions(RestRequest $request): array
    {
        $this->evaluated('actions', $request);

        return parent::actions($request);
    }

    #[\Override]
    public function limits(RestRequest $request): array
    {
        $this->evaluated('limits', $request);

        return [10, 25];
    }

    private function evaluated(string $declaration, RestRequest $request): void
    {
        app(EvaluatedDeclarations::class)->record($declaration, $request);
    }
}
