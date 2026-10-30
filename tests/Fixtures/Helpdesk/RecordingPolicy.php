<?php

namespace Lomkit\Rest\Tests\Fixtures\Helpdesk;

use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

use function Illuminate\Support\enum_value;

abstract class RecordingPolicy
{
    /** @var list<string> */
    public array $calls = [];

    /** @var array<string, Response> */
    public array $answers = [];

    public function viewAny(?Authenticatable $user): bool|Response
    {
        return $this->record('viewAny');
    }

    public function view(?Authenticatable $user, Model $model): bool|Response
    {
        return $this->record('view '.enum_value($model->getKey()));
    }

    public function create(?Authenticatable $user): bool|Response
    {
        return $this->record('create');
    }

    public function update(?Authenticatable $user, Model $model): bool|Response
    {
        return $this->record('update '.enum_value($model->getKey()));
    }

    public function updateAny(?Authenticatable $user): bool|Response
    {
        return $this->record('updateAny');
    }

    public function runActions(?Authenticatable $user): bool|Response
    {
        return $this->record('runActions');
    }

    public function deleteAny(?Authenticatable $user): bool|Response
    {
        return $this->record('deleteAny');
    }

    public function restoreAny(?Authenticatable $user): bool|Response
    {
        return $this->record('restoreAny');
    }

    public function forceDeleteAny(?Authenticatable $user): bool|Response
    {
        return $this->record('forceDeleteAny');
    }

    public function delete(?Authenticatable $user, Model $model): bool|Response
    {
        return $this->record('delete '.enum_value($model->getKey()));
    }

    public function restore(?Authenticatable $user, Model $model): bool|Response
    {
        return $this->record('restore '.enum_value($model->getKey()));
    }

    public function forceDelete(?Authenticatable $user, Model $model): bool|Response
    {
        return $this->record('forceDelete '.enum_value($model->getKey()));
    }

    protected function record(string $call): bool|Response
    {
        $this->calls[] = $call;

        return $this->answers[Str::before($call, ' ')] ?? true;
    }
}
