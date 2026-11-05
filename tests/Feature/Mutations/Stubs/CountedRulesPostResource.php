<?php

namespace Lomkit\Rest\Tests\Feature\Mutations\Stubs;

use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;

class CountedRulesPostResource extends PostResource
{
    public static int $rulesCalls = 0;

    public static int $createRulesCalls = 0;

    public static int $updateRulesCalls = 0;

    #[\Override]
    public function rules(): array
    {
        self::$rulesCalls++;

        return parent::rules();
    }

    #[\Override]
    public function createRules(): array
    {
        self::$createRulesCalls++;

        return parent::createRules();
    }

    #[\Override]
    public function updateRules(): array
    {
        self::$updateRulesCalls++;

        return parent::updateRules();
    }
}
