<?php

namespace Lomkit\Rest\Tests\Feature\Relations\Persisters\Stubs\Memberships;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\BelongsToMany;

class ProjectResource extends Resource
{
    public static string $model = Project::class;

    #[\Override]
    public function fields(): array
    {
        return [ID::make('id'), Text::make('name')];
    }

    #[\Override]
    public function relations(): array
    {
        return [
            BelongsToMany::make('members', MemberResource::class)
                ->pivotFields([Text::make('role')->nullable()])
                ->persistedBy(ProjectMemberships::class),
            BelongsToMany::make('everyone', MemberResource::class)->persistedBy(ProjectMemberships::class),
            BelongsToMany::make('staff', MemberResource::class),
        ];
    }
}
