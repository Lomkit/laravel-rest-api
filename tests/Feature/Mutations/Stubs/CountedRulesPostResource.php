<?php

namespace Lomkit\Rest\Tests\Feature\Mutations\Stubs;

use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;

class CountedRulesPostResource extends PostResource
{
    public static int $rulesCalls = 0;

    public static int $createRulesCalls = 0;

    public static int $updateRulesCalls = 0;

    #[\Override]
    public function rules(RestRequest $request): array
    {
        self::$rulesCalls++;

        return parent::rules($request);
    }

    #[\Override]
    public function createRules(RestRequest $request): array
    {
        self::$createRulesCalls++;

        return parent::createRules($request);
    }

    #[\Override]
    public function updateRules(RestRequest $request): array
    {
        self::$updateRulesCalls++;

        return parent::updateRules($request);
    }
}
