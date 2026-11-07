<?php

namespace Lomkit\Rest\Tests\Feature\MorphTargets\Stubs;

use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Tests\Fixtures\Library\Label;
use Lomkit\Rest\Tests\Fixtures\Library\OpenLibraryPolicy;

class TargetLabelPolicy extends OpenLibraryPolicy
{
    /** @var list<string> */
    public static array $calls = [];

    /** @var list<string> */
    public static array $denied = [];

    public function attachLabelable(?Authenticatable $user, Label $label, Model $target): Response
    {
        return $this->record('attachLabelable', $target);
    }

    public function detachLabelable(?Authenticatable $user, Label $label, Model $target): Response
    {
        return $this->record('detachLabelable', $target);
    }

    private function record(string $ability, Model $target): Response
    {
        $call = $ability.' '.class_basename($target).' '.$target->getKey();
        self::$calls[] = $call;

        return in_array($call, self::$denied, true) ? Response::deny('This target is locked.') : Response::allow();
    }
}
