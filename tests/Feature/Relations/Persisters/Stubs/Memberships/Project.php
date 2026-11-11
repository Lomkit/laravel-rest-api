<?php

namespace Lomkit\Rest\Tests\Feature\Relations\Persisters\Stubs\Memberships;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Lomkit\Rest\Tests\Fixtures\Archive\OpenArchivePolicy;

#[Unguarded]
#[UsePolicy(OpenArchivePolicy::class)]
class Project extends Model
{
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Member::class)
            ->withPivot('role')
            ->wherePivotNull('withdrawn_at');
    }

    public function everyone(): BelongsToMany
    {
        return $this->belongsToMany(Member::class)->withPivot('role');
    }

    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(Member::class)->where('members.name', '!=', 'Grace');
    }
}
