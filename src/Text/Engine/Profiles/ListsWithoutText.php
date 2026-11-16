<?php

namespace Lomkit\Rest\Text\Engine\Profiles;

interface ListsWithoutText extends EngineProfile
{
    public function matchAll(): string;

    public function listSortKeys(): int;
}
