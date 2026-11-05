<?php

namespace Lomkit\Rest\Tests\Feature\MorphTargets\Stubs;

use Closure;
use Lomkit\Rest\Relations\Relation;
use Lomkit\Rest\Tests\Fixtures\Library\LabelResource;

class DeclaredLabelResource extends LabelResource
{
    public static string $model = DeclaredLabel::class;

    /** @var (Closure(): list<Relation>)|null */
    public static ?Closure $declare = null;

    #[\Override]
    public function relations(): array
    {
        return self::$declare === null ? [] : (self::$declare)();
    }
}
