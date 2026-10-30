<?php

namespace Lomkit\Rest\Tests\Fixtures\Archive;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Unguarded]
#[UseFactory(DocumentFactory::class)]
#[UsePolicy(OpenArchivePolicy::class)]
class Document extends Model
{
    use HasFactory;
    use SoftDeletes;

    /** @return array<string, string> */
    #[\Override]
    protected function casts(): array
    {
        return ['archived' => 'boolean'];
    }
}
