<?php

namespace Lomkit\Rest\Tests\Unit\Authorization\Stubs;

use Illuminate\Database\Eloquent\Casts\AsEncryptedCollection;
use Illuminate\Database\Eloquent\Model;

final class EncryptedListDocument extends Model
{
    protected $table = 'documents';

    /** @return array<string, string> */
    #[\Override]
    protected function casts(): array
    {
        return ['secret' => AsEncryptedCollection::class];
    }
}
