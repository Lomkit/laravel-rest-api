<?php

namespace Lomkit\Rest\Tests\Unit\Authorization\Stubs;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;

final class PreparedDocument extends Model
{
    protected $table = 'documents';

    /** @return array<string, string> */
    #[\Override]
    protected function casts(): array
    {
        return ['meta' => AsArrayObject::class, 'settings' => SettingsCast::class];
    }
}
