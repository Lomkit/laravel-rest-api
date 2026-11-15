<?php

namespace Lomkit\Rest\Tests\Unit\Authorization\Stubs;

use Illuminate\Database\Eloquent\Model;

final class WalkedDocument extends Model
{
    public static int $walks = 0;

    protected $table = 'documents';

    /** @return array<string, mixed> */
    #[\Override]
    public function getRelations()
    {
        self::$walks++;

        return parent::getRelations();
    }

    /** @return array<string, string> */
    #[\Override]
    protected function casts(): array
    {
        return ['settings' => CountingSettingsCast::class];
    }
}
