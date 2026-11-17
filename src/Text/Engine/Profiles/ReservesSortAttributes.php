<?php

namespace Lomkit\Rest\Text\Engine\Profiles;

/** @internal */
interface ReservesSortAttributes extends EngineProfile
{
    /** @return list<string> */
    public function reservedSorts(): array;
}
