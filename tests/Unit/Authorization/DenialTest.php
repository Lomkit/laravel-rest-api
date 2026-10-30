<?php

namespace Lomkit\Rest\Tests\Unit\Authorization;

use Lomkit\Rest\Authorization\Check;
use Lomkit\Rest\Authorization\Decision;
use Lomkit\Rest\Authorization\Denial;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Channel;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\ChannelResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Team;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TeamResource;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DenialTest extends TestCase
{
    #[Test]
    public function it_names_the_path_the_ability_the_key_and_the_message_of_a_row(): void
    {
        $team = (new Team)->newFromBuilder(['id' => 7, 'name' => 'Billing']);
        $denial = Denial::of(new Check(new TeamResource, 'update', $team, 'mutate.0'), new Decision(false, 'Teams are locked.'));

        $this->assertSame(['path' => 'mutate.0', 'ability' => 'update', 'key' => 7, 'message' => 'Teams are locked.'], $denial->toArray());
        $this->assertSame(TeamResource::class, $denial->resource);
    }

    #[Test]
    public function it_names_the_value_of_a_key_cast_to_an_enum(): void
    {
        $email = (new Channel)->newFromBuilder(['code' => 'email', 'name' => 'Email']);
        $denial = Denial::of(new Check(new ChannelResource, 'update', $email, 'mutate.0'), new Decision(false, 'Channels are locked.'));

        $this->assertSame(['path' => 'mutate.0', 'ability' => 'update', 'key' => 'email', 'message' => 'Channels are locked.'], $denial->toArray());
    }

    #[Test]
    public function it_leaves_out_the_key_of_a_class_check(): void
    {
        $denial = Denial::of(Check::onClass(new TeamResource, 'viewAny', 'search.includes.0'), new Decision(false, 'Teams are not listed.'));

        $this->assertSame(['path' => 'search.includes.0', 'ability' => 'viewAny', 'message' => 'Teams are not listed.'], $denial->toArray());
    }

    #[Test]
    public function it_leaves_out_the_key_of_a_model_that_is_not_saved(): void
    {
        $denial = Denial::of(new Check(new TeamResource, 'create', new Team(['id' => 9, 'name' => 'Billing']), 'mutate.1'), new Decision(false, 'Teams are full.'));

        $this->assertNull($denial->key);
    }

    #[Test]
    public function it_gives_the_default_message_to_a_denial_without_one(): void
    {
        $denial = Denial::of(Check::onClass(new TeamResource, 'viewAny'), new Decision(false));

        $this->assertSame(['path' => '', 'ability' => 'viewAny', 'message' => 'This action is unauthorized.'], $denial->toArray());
    }
}
