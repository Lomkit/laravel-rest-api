<?php

namespace Lomkit\Rest\Tests\Unit\Authorization;

use Lomkit\Rest\Authorization\Check;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Channel;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\ChannelResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Team;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TeamResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CheckTest extends TestCase
{
    #[Test]
    public function it_keys_a_check_by_resource_ability_and_model(): void
    {
        $check = new Check(new TeamResource, 'update', $this->team(1));

        $this->assertSame(
            'Lomkit\Rest\Tests\Fixtures\Helpdesk\TeamResource|update|Lomkit\Rest\Tests\Fixtures\Helpdesk\Team#1',
            $check->key(),
        );
    }

    #[Test]
    public function it_keys_a_class_check_by_the_model_class(): void
    {
        $this->assertSame(
            'Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource|viewAny|Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket',
            Check::onClass(new TicketResource, 'viewAny')->key(),
        );
    }

    #[Test]
    public function it_adds_the_related_models_to_the_key(): void
    {
        $check = new Check(new TeamResource, 'attachTeam', $this->team(1), related: [$this->team(2)]);

        $this->assertSame(
            'Lomkit\Rest\Tests\Fixtures\Helpdesk\TeamResource|attachTeam|Lomkit\Rest\Tests\Fixtures\Helpdesk\Team#1|Lomkit\Rest\Tests\Fixtures\Helpdesk\Team#2',
            $check->key(),
        );
        $this->assertSame([$check->subject, ...$check->related], $check->arguments());
    }

    #[Test]
    public function it_keys_an_unsaved_model_by_its_instance(): void
    {
        $team = new Team;

        $this->assertSame(
            'Lomkit\Rest\Tests\Fixtures\Helpdesk\TeamResource|create|Lomkit\Rest\Tests\Fixtures\Helpdesk\Team#instance:'.spl_object_id($team),
            (new Check(new TeamResource, 'create', $team))->key(),
        );
        $this->assertNotSame((new Check(new TeamResource, 'create', $team))->key(), (new Check(new TeamResource, 'create', new Team))->key());
    }

    #[Test]
    public function it_encodes_a_model_key_holding_a_separator(): void
    {
        $team = $this->team('a|b')->setKeyType('string')->setIncrementing(false);

        $this->assertSame(
            'Lomkit\Rest\Tests\Fixtures\Helpdesk\TeamResource|view|Lomkit\Rest\Tests\Fixtures\Helpdesk\Team#a%7Cb',
            (new Check(new TeamResource, 'view', $team))->key(),
        );
    }

    #[Test]
    public function it_keys_a_model_by_the_value_of_an_enum_key(): void
    {
        $check = new Check(new ChannelResource, 'view', (new Channel)->newFromBuilder(['code' => 'email', 'name' => 'Email']));

        $this->assertSame(
            'Lomkit\Rest\Tests\Fixtures\Helpdesk\ChannelResource|view|Lomkit\Rest\Tests\Fixtures\Helpdesk\Channel#email',
            $check->key(),
        );
        $this->assertSame(['channels#email'], $check->rows());
        $this->assertFalse($check->isKeyedByInstance());
    }

    #[Test]
    public function it_keys_a_model_whose_key_is_not_a_scalar_by_its_instance(): void
    {
        $team = $this->team(['site' => 'Lyon', 'number' => 1])->setIncrementing(false);
        $check = new Check(new TeamResource, 'view', $team);

        $this->assertSame(
            'Lomkit\Rest\Tests\Fixtures\Helpdesk\TeamResource|view|Lomkit\Rest\Tests\Fixtures\Helpdesk\Team#instance:'.spl_object_id($team),
            $check->key(),
        );
        $this->assertSame(['instance:'.spl_object_id($team)], $check->rows());
        $this->assertTrue($check->isKeyedByInstance());
    }

    #[Test]
    public function it_names_the_rows_of_its_subject_and_related_models_by_table_and_key(): void
    {
        $check = new Check(new TeamResource, 'attachTeam', $this->team(1), related: [$this->team(2)]);

        $this->assertSame(['teams#1', 'teams#2'], $check->rows());
        $this->assertSame([], Check::onClass(new TeamResource, 'viewAny')->rows());
    }

    #[Test]
    public function it_names_a_saved_model_by_its_row_and_its_instance(): void
    {
        $team = $this->team(1);
        $draft = new Team;

        $this->assertSame(['teams#1', 'instance:'.spl_object_id($team)], Check::rowsOf($team));
        $this->assertSame(['instance:'.spl_object_id($draft)], Check::rowsOf($draft));
    }

    #[Test]
    public function it_tells_whether_a_model_is_keyed_by_its_instance(): void
    {
        $this->assertTrue((new Check(new TeamResource, 'create', new Team))->isKeyedByInstance());
        $this->assertFalse((new Check(new TeamResource, 'view', $this->team(1)))->isKeyedByInstance());
        $this->assertFalse(Check::onClass(new TeamResource, 'viewAny')->isKeyedByInstance());
    }

    /** @param int|string|array<string, int|string> $id */
    private function team(int|string|array $id): Team
    {
        return (new Team)->newFromBuilder(['id' => $id, 'name' => 'Billing']);
    }
}
