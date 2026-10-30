<?php

namespace Lomkit\Rest\Tests\Fixtures\Workspace;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

#[Unguarded]
#[UseFactory(NoteFactory::class)]
#[UsePolicy(OpenWorkspacePolicy::class)]
class Note extends Model
{
    use HasFactory;
    use Searchable;

    /** @return array<string, mixed> */
    public function toSearchableArray(): array
    {
        return ['title' => $this->title];
    }

    /** @return array<string, string> */
    #[\Override]
    protected function casts(): array
    {
        return ['pinned' => 'boolean'];
    }
}
