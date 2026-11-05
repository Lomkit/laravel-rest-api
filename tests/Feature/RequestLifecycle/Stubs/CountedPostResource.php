<?php

namespace Lomkit\Rest\Tests\Feature\RequestLifecycle\Stubs;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;

class CountedPostResource extends PostResource
{
    #[\Override]
    public function fields(): array
    {
        $this->evaluated('fields');
        $user = $this->request()->user();

        if ($user?->getAttribute('is_staff')) {
            return [ID::make('id'), Text::make('title'), Integer::make('views')];
        }

        return [ID::make('id'), Text::make('title')];
    }

    #[\Override]
    public function scopes(): array
    {
        $this->evaluated('scopes');

        return parent::scopes();
    }

    #[\Override]
    public function relations(): array
    {
        $this->evaluated('relations');

        return parent::relations();
    }

    #[\Override]
    public function instructions(): array
    {
        $this->evaluated('instructions');

        return parent::instructions();
    }

    #[\Override]
    public function actions(): array
    {
        $this->evaluated('actions');

        return parent::actions();
    }

    #[\Override]
    public function limits(): array
    {
        $this->evaluated('limits');

        return [10, 25, 50];
    }

    private function evaluated(string $declaration): void
    {
        app(EvaluatedDeclarations::class)->record($declaration, $this->request());
    }
}
