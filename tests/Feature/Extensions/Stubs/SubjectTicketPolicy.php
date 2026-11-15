<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketPolicy;

class SubjectTicketPolicy extends TicketPolicy
{
    /** @var list<array{int, mixed}> */
    public array $subjects = [];

    #[\Override]
    public function update(?Authenticatable $user, Model $model): Response
    {
        $this->subjects[] = [spl_object_id($model), $model->getAttribute((string) AugmentedPreparingExtension::$alias)];

        return parent::update($user, $model);
    }
}
