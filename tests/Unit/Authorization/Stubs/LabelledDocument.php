<?php

namespace Lomkit\Rest\Tests\Unit\Authorization\Stubs;

use Illuminate\Database\Eloquent\Model;

final class LabelledDocument extends Model
{
    protected $table = 'documents';

    /** @return array<string, string> */
    #[\Override]
    protected function casts(): array
    {
        return ['label' => LabelCast::class];
    }
}
